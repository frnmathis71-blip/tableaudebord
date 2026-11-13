<?php

declare(strict_types=1);

namespace Tdb;

/**
 * Source « Agenda Apple » : calendriers iCloud lus par leur lien de partage public (lecture seule).
 * Les liens sont stockés chiffrés. Les événements sont recopiés dans la base à chaque mise à jour,
 * pour que la page s'affiche vite et reste visible si iCloud ne répond pas (mode dégradé).
 */
final class Agenda
{
    /** Délai minimal entre deux mises à jour déclenchées à l'ouverture de l'appli. */
    public const DELAI_OUVERTURE = 180;
    private const TAILLE_MAX = 5 * 1024 * 1024;
    private const JOURS_PASSES = 1;
    private const JOURS_FUTURS = 15;

    /** @var callable|null Remplace le téléchargement (tests). */
    private $telecharger = null;

    public function __construct(private Db $db, private Config $config)
    {
    }

    public function remplacerTelechargement(callable $fonction): void
    {
        $this->telecharger = $fonction;
    }

    private function crypto(): Crypto
    {
        return new Crypto($this->config->cle());
    }

    // ----------------------------------------------------------- Calendriers

    public function calendriers(): array
    {
        return $this->db->lignes('SELECT id, name, last_sync_at, last_attempt_at, last_error, event_count FROM calendars ORDER BY id');
    }

    /** Vérifie et normalise un lien de partage. Renvoie null s'il n'est pas accepté. */
    public function lienValide(string $lien): ?string
    {
        $lien = trim($lien);
        if (str_starts_with(strtolower($lien), 'webcal://')) {
            $lien = 'https://' . substr($lien, 9);
        }
        $parties = parse_url($lien);
        if (!is_array($parties) || !isset($parties['scheme'], $parties['host'])) {
            return null;
        }
        $schema = strtolower($parties['scheme']);
        $hote = strtolower($parties['host']);
        if (isset($parties['user']) || isset($parties['pass'])) {
            return null;
        }
        if (!$this->hoteAutorise($hote)) {
            return null;
        }
        $local = in_array($hote, ['localhost', '127.0.0.1'], true);
        if ($schema !== 'https' && !($local && $schema === 'http')) {
            return null;
        }

        return $lien;
    }

    private function hoteAutorise(string $hote): bool
    {
        foreach ((array) $this->config->get('calendar_hosts', ['icloud.com']) as $autorise) {
            $autorise = strtolower((string) $autorise);
            if ($hote === $autorise || str_ends_with($hote, '.' . $autorise)) {
                return true;
            }
        }

        return false;
    }

    public function ajouter(string $nom, string $lien): int
    {
        $this->db->executer(
            'INSERT INTO calendars (name, url_enc, created_at, event_count) VALUES (?, ?, ?, 0)',
            [mb_substr(trim($nom), 0, 60), $this->crypto()->chiffrer($lien), Clock::sql()]
        );

        return (int) $this->db->pdo->lastInsertId();
    }

    public function supprimer(int $id): void
    {
        $this->db->executer('DELETE FROM events WHERE calendar_id = ?', [$id]);
        $this->db->executer('DELETE FROM calendars WHERE id = ?', [$id]);
    }

    // ------------------------------------------------------- Mises à jour

    /** Met à jour les calendriers dont la dernière tentative date de plus de $delai secondes. */
    public function synchroniser(int $delai = 0, string $origine = 'appli'): array
    {
        $resultat = ['mis_a_jour' => 0, 'erreurs' => 0, 'change' => false];
        foreach ($this->db->lignes('SELECT * FROM calendars ORDER BY id') as $cal) {
            $derniere = $cal['last_attempt_at'] !== null ? Clock::depuisSql($cal['last_attempt_at']) : 0;
            if (Clock::now() - $derniere < $delai) {
                continue;
            }
            // Réserve la mise à jour (évite deux téléchargements simultanés du même calendrier)
            $reserve = $this->db->executer(
                'UPDATE calendars SET last_attempt_at = ? WHERE id = ? AND (last_attempt_at IS NULL OR last_attempt_at = ?)',
                [Clock::sql(), $cal['id'], $cal['last_attempt_at']]
            )->rowCount();
            if ($reserve === 0) {
                continue;
            }
            try {
                $change = $this->synchroniserUn($cal);
                $resultat['mis_a_jour']++;
                $resultat['change'] = $resultat['change'] || $change;
                $this->db->journal('source_lue', 'agenda « ' . $cal['name'] . ' » (' . $origine . ')');
            } catch (\Throwable $e) {
                $resultat['erreurs']++;
                $message = $e instanceof \RuntimeException ? $e->getMessage() : 'Fichier d’agenda illisible.';
                $this->db->executer('UPDATE calendars SET last_error = ? WHERE id = ?', [mb_substr($message, 0, 250), $cal['id']]);
                $this->db->journal('source_en_erreur', 'agenda « ' . $cal['name'] . ' » : ' . $message);
            }
        }

        return $resultat;
    }

    /** Renvoie true si les événements ont changé. */
    private function synchroniserUn(array $cal): bool
    {
        $lien = $this->crypto()->dechiffrer($cal['url_enc']);
        if ($lien === null || $this->lienValide($lien) === null) {
            throw new \RuntimeException('Lien du calendrier illisible : supprime-le et ajoute-le à nouveau.');
        }
        $contenu = $this->telecharger !== null ? ($this->telecharger)($lien) : $this->telechargerLien($lien);
        if (!str_contains(substr($contenu, 0, 2000), 'BEGIN:VCALENDAR')) {
            throw new \RuntimeException('Le lien ne renvoie pas un agenda. Vérifie que le calendrier est toujours public.');
        }

        $aujourdhui = Planning::debutJour(Clock::now());
        $evenements = Ics::evenements(
            $contenu,
            $aujourdhui->modify('-' . self::JOURS_PASSES . ' day')->getTimestamp(),
            $aujourdhui->modify('+' . self::JOURS_FUTURS . ' day')->getTimestamp()
        );

        $empreinte = hash('sha256', (string) json_encode($evenements));
        $change = $empreinte !== (string) ($cal['fingerprint'] ?? '');

        $this->db->pdo->beginTransaction();
        try {
            if ($change) {
                $this->db->executer('DELETE FROM events WHERE calendar_id = ?', [$cal['id']]);
                $requete = $this->db->pdo->prepare(
                    'INSERT INTO events (calendar_id, uid, title, location, all_day, starts_at, ends_at, day_start, day_end)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                foreach ($evenements as $e) {
                    $requete->execute([
                        $cal['id'], $e['uid'], $e['titre'], $e['lieu'], $e['journee'] ? 1 : 0,
                        Clock::sql($e['debut']), Clock::sql($e['fin']), $e['jour_debut'], $e['jour_fin'],
                    ]);
                }
            }
            $this->db->executer(
                'UPDATE calendars SET last_sync_at = ?, last_error = NULL, event_count = ?, fingerprint = ? WHERE id = ?',
                [Clock::sql(), count($evenements), $empreinte, $cal['id']]
            );
            $this->db->pdo->commit();
        } catch (\Throwable $e) {
            $this->db->pdo->rollBack();
            throw $e;
        }

        return $change;
    }

    /** Téléchargement en lecture seule, avec contrôle de l'adresse à chaque redirection. */
    private function telechargerLien(string $lien): string
    {
        for ($i = 0; $i < 4; $i++) {
            $donnees = '';
            $trop = false;
            $ch = curl_init($lien);
            curl_setopt_array($ch, [
                CURLOPT_HTTPGET => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
                CURLOPT_USERAGENT => 'TableauDeBord/1.0',
                CURLOPT_HTTPHEADER => ['Accept: text/calendar'],
                CURLOPT_ENCODING => '',
                CURLOPT_WRITEFUNCTION => static function ($ch, string $morceau) use (&$donnees, &$trop): int {
                    $donnees .= $morceau;
                    if (strlen($donnees) > self::TAILLE_MAX) {
                        $trop = true;

                        return 0;
                    }

                    return strlen($morceau);
                },
            ]);
            $ok = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $redirection = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            if ($trop) {
                throw new \RuntimeException('Agenda trop volumineux.');
            }
            if ($ok === false) {
                throw new \RuntimeException('iCloud ne répond pas pour le moment.');
            }
            if ($code >= 300 && $code < 400 && $redirection !== '') {
                $suivant = $this->lienValide($redirection);
                if ($suivant === null) {
                    throw new \RuntimeException('Redirection vers une adresse non autorisée.');
                }
                $lien = $suivant;
                continue;
            }
            if ($code === 404 || $code === 403) {
                throw new \RuntimeException('Calendrier introuvable : il n’est peut-être plus public.');
            }
            if ($code !== 200) {
                throw new \RuntimeException('iCloud a répondu avec une erreur (' . $code . ').');
            }

            return $donnees;
        }
        throw new \RuntimeException('Trop de redirections.');
    }

    // --------------------------------------------------------- Lecture

    /** Événements enregistrés, au format de Planning. */
    public function evenements(): array
    {
        $lignes = $this->db->lignes(
            'SELECT e.*, c.name AS calendar_name FROM events e JOIN calendars c ON c.id = e.calendar_id ORDER BY e.starts_at, e.title'
        );

        return array_map(static fn (array $l): array => [
            'titre' => $l['title'],
            'lieu' => $l['location'],
            'calendrier' => $l['calendar_name'],
            'journee' => (int) $l['all_day'] === 1,
            'debut' => Clock::depuisSql($l['starts_at']),
            'fin' => Clock::depuisSql($l['ends_at']),
            'jour_debut' => $l['day_start'],
            'jour_fin' => $l['day_end'],
        ], $lignes);
    }

    /** État de la source pour l'affichage (mode dégradé). */
    public function etat(): array
    {
        $cals = $this->calendriers();
        $erreurs = array_values(array_filter($cals, static fn (array $c): bool => $c['last_error'] !== null));
        $dates = array_filter(array_column($cals, 'last_sync_at'));

        return [
            'configure' => $cals !== [],
            'erreurs' => $erreurs,
            'derniere_maj' => $dates === [] ? null : min($dates),
        ];
    }
}
