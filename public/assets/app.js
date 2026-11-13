/* Tableau de bord — comportements de l'interface (sans dépendance externe). */
(() => {
    'use strict';

    // ---------------------------------------------------------------- Outils

    const csrf = document.querySelector('meta[name="csrf"]')?.content || '';
    const zoneMessage = document.getElementById('message');
    let minuterie;

    function message(texte, erreur = false) {
        if (!zoneMessage) return;
        zoneMessage.textContent = texte;
        zoneMessage.classList.toggle('erreur', erreur);
        zoneMessage.hidden = false;
        clearTimeout(minuterie);
        minuterie = setTimeout(() => { zoneMessage.hidden = true; }, 4500);
    }

    async function api(chemin, donnees = {}) {
        let reponse;
        try {
            reponse = await fetch(chemin, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF': csrf },
                body: JSON.stringify(donnees),
            });
        } catch {
            throw new Error('Pas de connexion Internet.');
        }
        const json = await reponse.json().catch(() => ({}));
        if (reponse.status === 401 && csrf) {
            location.href = '/connexion';
        }
        if (!reponse.ok || !json.ok) {
            throw new Error(json.erreur || 'Une erreur est survenue.');
        }
        return json;
    }

    const b64 = {
        versOctets(texte) {
            const propre = texte.replace(/-/g, '+').replace(/_/g, '/');
            return Uint8Array.from(atob(propre), (c) => c.charCodeAt(0));
        },
        depuisOctets(tampon) {
            let binaire = '';
            new Uint8Array(tampon).forEach((o) => { binaire += String.fromCharCode(o); });
            return btoa(binaire).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        },
    };

    function clesDisponibles() {
        if (!window.PublicKeyCredential) {
            message('Cet appareil ou ce navigateur ne gère pas les clés d’accès.', true);
            return false;
        }
        return true;
    }

    /** L'utilisateur a fermé la fenêtre Face ID : ce n'est pas une erreur à afficher en rouge. */
    function annulation(e) {
        return e && (e.name === 'NotAllowedError' || e.name === 'AbortError');
    }

    async function occuper(bouton, travail) {
        if (bouton.disabled) return;
        bouton.disabled = true;
        try {
            await travail();
        } catch (e) {
            message(annulation(e) ? 'Action annulée.' : e.message, !annulation(e));
        } finally {
            bouton.disabled = false;
        }
    }

    // ------------------------------------------------- Connexion par clé d'accès

    async function seConnecter() {
        const { options } = await api('/api/connexion/options');
        const pk = options.publicKey;
        pk.challenge = b64.versOctets(pk.challenge);
        const cle = await navigator.credentials.get({ publicKey: pk });
        await api('/api/connexion/valider', {
            id: cle.id,
            clientDataJSON: b64.depuisOctets(cle.response.clientDataJSON),
            authenticatorData: b64.depuisOctets(cle.response.authenticatorData),
            signature: b64.depuisOctets(cle.response.signature),
        });
        location.href = '/';
    }

    async function creerCle(suite) {
        const { options } = await api('/api/cle/options');
        const pk = options.publicKey;
        pk.challenge = b64.versOctets(pk.challenge);
        pk.user.id = b64.versOctets(pk.user.id);
        pk.excludeCredentials = (pk.excludeCredentials || []).map((c) => ({ ...c, id: b64.versOctets(c.id) }));
        const cle = await navigator.credentials.create({ publicKey: pk });
        const resultat = await api('/api/cle/valider', {
            clientDataJSON: b64.depuisOctets(cle.response.clientDataJSON),
            attestationObject: b64.depuisOctets(cle.response.attestationObject),
        });
        if (resultat.codeSecours) {
            // Le code de secours vient de servir : on affiche le nouveau avant de continuer.
            document.getElementById('bloc-cle').hidden = true;
            document.getElementById('code-secours').textContent = resultat.codeSecours;
            document.getElementById('bloc-code').hidden = false;
            document.getElementById('code-note').checked = false;
            document.getElementById('code-note').dataset.mode = 'continuer';
            return;
        }
        location.href = suite || '/';
    }

    document.querySelectorAll('[data-action="connexion"]').forEach((bouton) => {
        bouton.addEventListener('click', () => clesDisponibles() && occuper(bouton, seConnecter));
    });

    document.querySelectorAll('[data-action="creer-cle"]').forEach((bouton) => {
        bouton.addEventListener('click', () => clesDisponibles() && occuper(bouton, () => creerCle(bouton.dataset.suite)));
    });

    // Case « J'ai noté mon code » : débloque l'étape suivante
    const caseCode = document.getElementById('code-note');
    if (caseCode) {
        caseCode.addEventListener('change', () => {
            if (caseCode.dataset.mode === 'continuer') {
                document.getElementById('continuer').hidden = !caseCode.checked;
            } else {
                const bouton = document.querySelector('[data-action="creer-cle"]');
                if (bouton) bouton.disabled = !caseCode.checked;
            }
        });
    }

    const formSecours = document.getElementById('form-secours');
    if (formSecours) {
        formSecours.addEventListener('submit', (e) => {
            e.preventDefault();
            const bouton = formSecours.querySelector('button');
            occuper(bouton, async () => {
                const { suite } = await api('/api/secours', { code: formSecours.code.value });
                location.href = suite;
            });
        });
    }

    // ------------------------------------------------------ Modules déplaçables

    const liste = document.getElementById('modules');
    const boutonOrganiser = document.getElementById('organiser');

    if (liste && boutonOrganiser) {
        const majFleches = () => {
            const cartes = [...liste.children];
            cartes.forEach((carte, i) => {
                carte.querySelector('[data-geste="haut"]').disabled = i === 0;
                carte.querySelector('[data-geste="bas"]').disabled = i === cartes.length - 1;
            });
        };

        const enregistrer = async () => {
            const modules = [...liste.children].map((carte) => ({
                id: carte.dataset.id,
                visible: carte.dataset.visible === '1',
            }));
            try {
                await api('/api/modules', { modules });
            } catch (e) {
                message(e.message, true);
            }
        };

        boutonOrganiser.addEventListener('click', () => {
            const actif = document.body.classList.toggle('organisation');
            boutonOrganiser.textContent = actif ? 'Terminé' : 'Organiser';
            boutonOrganiser.setAttribute('aria-pressed', String(actif));
            document.getElementById('aide-organiser').hidden = !actif;
            majFleches();
        });

        liste.addEventListener('click', (e) => {
            const bouton = e.target.closest('[data-geste]');
            if (!bouton) return;
            const carte = bouton.closest('.module');
            const geste = bouton.dataset.geste;
            if (geste === 'haut' && carte.previousElementSibling) {
                liste.insertBefore(carte, carte.previousElementSibling);
            } else if (geste === 'bas' && carte.nextElementSibling) {
                liste.insertBefore(carte.nextElementSibling, carte);
            } else if (geste === 'voir') {
                const visible = carte.dataset.visible !== '1';
                carte.dataset.visible = visible ? '1' : '0';
                carte.classList.toggle('masque', !visible);
                bouton.setAttribute('aria-pressed', String(visible));
            }
            majFleches();
            bouton.focus();
            enregistrer();
        });
    }

    // ------------------------------------------------------------------ Réglages

    const actions = {
        deconnexion: async () => {
            await api('/api/deconnexion');
            location.href = '/connexion';
        },
        revoquer: async (bouton) => {
            await api('/api/sessions/revoquer', { id: bouton.dataset.id });
            location.reload();
        },
        'revoquer-autres': async () => {
            await api('/api/sessions/revoquer', { autres: true });
            location.reload();
        },
        'supprimer-cle': async (bouton) => {
            if (!confirm('Supprimer cette clé d’accès ? L’appareil concerné ne pourra plus se connecter avec.')) return;
            await api('/api/cle/supprimer', { id: Number(bouton.dataset.id) });
            location.reload();
        },
        'nouveau-code': async () => {
            if (!confirm('Créer un nouveau code de secours ? L’ancien ne fonctionnera plus.')) return;
            const { codeSecours } = await api('/api/secours/nouveau');
            const zone = document.getElementById('code-secours');
            zone.textContent = codeSecours;
            zone.hidden = false;
            message('Note ce code sur papier : il ne sera plus affiché.');
        },
    };

    Object.entries(actions).forEach(([nom, action]) => {
        document.querySelectorAll(`[data-action="${nom}"]`).forEach((bouton) => {
            bouton.addEventListener('click', () => occuper(bouton, () => action(bouton)));
        });
    });

    // -------------------------------------------------------------------- Agenda

    // À l'ouverture : mise à jour de l'agenda en arrière-plan ; la page se recharge si quelque chose a changé.
    const journee = document.getElementById('journee');
    const boutonActualiser = document.querySelector('[data-action="actualiser"]');

    async function actualiserAgenda(forcer) {
        const resultat = await api('/api/agenda/rafraichir', { forcer });
        if (resultat.change) {
            location.reload();
        } else if (forcer) {
            message(resultat.erreurs > 0 ? 'iCloud ne répond pas : affichage de la dernière version.' : 'Agenda à jour.', resultat.erreurs > 0);
        }
    }

    if (journee) {
        actualiserAgenda(false).catch(() => {});
    }
    if (boutonActualiser) {
        boutonActualiser.addEventListener('click', () => {
            boutonActualiser.classList.add('tourne');
            occuper(boutonActualiser, () => actualiserAgenda(true))
                .finally(() => boutonActualiser.classList.remove('tourne'));
        });
    }

    const formCalendrier = document.getElementById('form-calendrier');
    if (formCalendrier) {
        formCalendrier.addEventListener('submit', (e) => {
            e.preventDefault();
            const bouton = formCalendrier.querySelector('button');
            occuper(bouton, async () => {
                const resultat = await api('/api/agenda/ajouter', {
                    nom: formCalendrier.nom.value,
                    lien: formCalendrier.lien.value,
                });
                if (resultat.erreur_lecture) {
                    alert('Calendrier ajouté, mais sa lecture a échoué : ' + resultat.erreur_lecture);
                }
                location.reload();
            });
        });
    }

    document.querySelectorAll('[data-action="supprimer-calendrier"]').forEach((bouton) => {
        bouton.addEventListener('click', () => occuper(bouton, async () => {
            if (!confirm(`Retirer le calendrier « ${bouton.dataset.nom} » ? Ses événements disparaîtront du tableau de bord (rien n’est supprimé sur ton iPhone).`)) return;
            await api('/api/agenda/supprimer', { id: Number(bouton.dataset.id) });
            location.reload();
        }));
    });

    // ------------------------------------------- Installation sur l'écran d'accueil

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    }
})();
