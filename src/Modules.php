<?php

declare(strict_types=1);

namespace Tdb;

/**
 * Les modules du tableau de bord. Chacun répond à une question précise.
 * L'ordre et la visibilité choisis sont enregistrés dans les réglages.
 */
final class Modules
{
    /** Catalogue des modules du MVP. Ils sont vides tant que leur mission n'est pas faite. */
    public const CATALOGUE = [
        'agenda' => [
            'titre' => 'Agenda',
            'question' => 'Qu’est-ce qui est prévu cette semaine ?',
            'mission' => 'M2',
            'icone' => 'agenda',
        ],
        'ecole' => [
            'titre' => 'École',
            'question' => 'Quels cours aujourd’hui, et qu’est-ce qui a changé ?',
            'mission' => 'M3',
            'icone' => 'ecole',
        ],
        'pro' => [
            'titre' => 'Pro et URSSAF',
            'question' => 'Quand est ma prochaine déclaration ?',
            'mission' => 'M4',
            'icone' => 'pro',
        ],
        'stage' => [
            'titre' => 'Recherche de stage',
            'question' => 'Qui dois-je relancer ?',
            'mission' => 'M5',
            'icone' => 'stage',
        ],
    ];

    public function __construct(private Db $db)
    {
    }

    /**
     * Liste ordonnée des modules avec leur visibilité.
     * @return list<array{id: string, visible: bool, titre: string, question: string, mission: string, icone: string}>
     */
    public function disposition(): array
    {
        $enregistre = json_decode($this->db->reglage('modules', '[]') ?? '[]', true);
        $resultat = [];
        $vus = [];
        foreach (is_array($enregistre) ? $enregistre : [] as $element) {
            $id = is_array($element) ? (string) ($element['id'] ?? '') : '';
            if (isset(self::CATALOGUE[$id]) && !isset($vus[$id])) {
                $vus[$id] = true;
                $resultat[] = ['id' => $id, 'visible' => (bool) ($element['visible'] ?? true)] + self::CATALOGUE[$id];
            }
        }
        // Les modules ajoutés plus tard arrivent à la fin, visibles
        foreach (self::CATALOGUE as $id => $infos) {
            if (!isset($vus[$id])) {
                $resultat[] = ['id' => $id, 'visible' => true] + $infos;
            }
        }

        return $resultat;
    }

    /** Enregistre l'ordre et la visibilité. Renvoie false si la demande est invalide. */
    public function enregistrer(mixed $liste): bool
    {
        if (!is_array($liste)) {
            return false;
        }
        $propre = [];
        $vus = [];
        foreach ($liste as $element) {
            $id = is_array($element) ? ($element['id'] ?? null) : null;
            if (!is_string($id) || !isset(self::CATALOGUE[$id]) || isset($vus[$id])) {
                return false;
            }
            $vus[$id] = true;
            $propre[] = ['id' => $id, 'visible' => (bool) ($element['visible'] ?? true)];
        }
        if (count($propre) !== count(self::CATALOGUE)) {
            return false;
        }
        $this->db->definirReglage('modules', (string) json_encode($propre));

        return true;
    }
}
