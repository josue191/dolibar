====================================================================
  MODULE APC LOGISTICS & PROCUREMENT  —  Dolibarr Custom Module
  (c) 2026 APC ONG — Agri-Peace and Child — Goma, RD Congo
  Licence : GNU GPL v3
====================================================================

MODULE      : apclogistics
VERSION     : 1.0.0
N° MODULE   : 500100
AUTEUR      : APC ONG / Departement Logistique
DEPENDANCES : Dolibarr >= 17 LTS (teste 17.x et 18.x)
              PHP      >= 8.1  (avec extensions : pdo_mysql, gd, mbstring,
                                 zip, dom, xml, json)
              MySQL    >= 5.7 ou MariaDB >= 10.5
              Modules Dolibarr obligatoirement actives :
                 — Produits/Stock   (modProduct, modStock)
                 — Tiers            (modSociete)
                 — Fournisseurs     (modFournisseur)

====================================================================
  SOMMAIRE
====================================================================

  1 — Qu'est-ce que le module apclogistics ?
  2 — Installation (3 methodes)
  3 — Activation et premier parametrage
  4 — Contenu de l'arborescence
  5 — Mise a jour
  6 — Desinstallation
  7 — Support / Contact

====================================================================
1 — QU'EST-CE QUE LE MODULE apclogistics ?
====================================================================

  Module natif (custom module) integre a Dolibarr ERP/CRM pour la
  digitalisation complete de la chaine logistique et financiere de
  l'ONG APC :

    CYCLE LOGISTIQUE / ACHATS
      1. Etat de besoin (planification budgetaire, 3 signatures)
      2. Requisition / Bon de sortie magasin
      3. Demande de prix -> envoi via LIEN UNIQUE SECURISE aux
                            fournisseurs
      4. Cotation fournisseur (formulaire web externe, sans
         compte Dolibarr : portail public responsive)
      5. Transformation 1-clic -> Bon de commande
      6. Bon de reception -> mise a jour AUTOMATIQUE du stock
      7. Fiche de stock (mouvements perpétuels : entree / sortie)

    CYCLE FINANCIER
      8. Demande d'avance       (3 signatures)
      9. Justification d'avance (calcul automatique de l'ECART :
                                 total depense vs avance percue)
     10. Demande de paiement    (3 cas d'usage, 3 signatures)

  LES 10 FORMULAIRES PDF sont generes en UN CLIC, conformes a la
  trame institutionnelle APC : logo, en-tete, mentions legales,
  tableaux exacts, zones de signatures. Support multi-pages.

====================================================================
2 — INSTALLATION (3 METHODES)
====================================================================

--- METHODE RECOMMANDEE : Zip a deployer dans Dolibarr Admin

  1. Zipper l'INTÉGRALITÉ du dossier  /apclogistics/  (le dossier
     apclogistics/ doit se trouver a la RACINE du zip — pas le
     contenu directement).
  2. Connectez-vous en administrateur Dolibarr.
  3. Menu Administration -> Modules/Générateurs d'applications.
  4. Onglet "Déployer / Installer un module externe".
  5. Cliquez "Parcourir", choisissez le zip, cliquez "Envoyer".
  6. Cliquez "Activer" en face de "APC Logistique & Achats".
  7. Le menu "APC Logistique" apparait dans la sidebar gauche.
  8. Cliquez sur le bouton de configuration (roue crantee) pour
     saisir le logo officiel APC, les coordonnees, l'arrete
     ministeriel, l'email de notification logisticien, etc.

--- METHODE 2 : Copie SFTP / FTP dans /htdocs/custom/

  1. Avec un client FTP/SFTP, connectez-vous au serveur cPanel.
  2. Accedez au dossier racine de votre Dolibarr, descendez dans
           .../htdocs/custom/
  3. Copiez le dossier entier apclogistics/ dedans.
  4. CHMOD : 755 sur les dossiers, 644 sur les fichiers PHP.
  5. Connectez-vous admin Dolibarr -> Modules.
  6. Le module "APC Logistique & Achats" apparait en bas de la
     liste, cliquez "Activer". Puis configurer (roue).

--- METHODE 3 : Developpement local / WAMP / XAMPP

  1. Installer Dolibarr localement.
  2. Copier le dossier apclogistics/ dans htdocs/custom/.
  3. Activer dans Admin > Modules.
  4. Prerequis PHP local : activer extension=gd, openssl, pdo_mysql,
     mbstring, zip, dom, xmlwriter.

====================================================================
3 — ACTIVATION ET PREMIER PARAMETRAGE (A FAIRE IMPERATIVEMENT)
====================================================================

  Une fois le module active, allez dans :

    Administration  ->  Modules  ->  roue "Config" a cote de
    "APC Logistique & Achats"

  Remplir :
    a. LOGO APC : uploader le fichier PNG 300 DPI (1000x1000 min)
       — jusqu'a nouvel ordre, un placeholder vert "APC ONG" est
         utilise automatiquement.
    b. Texte EN-TETE APC (nom, adresse Goma, telephones, email,
       arrete ministeriel N°308/CAB/M.E/J&GS/2023).
    c. MENTION LEGALE DEMANDE DE PRIX (par defaut celle
       d'Excel, modifiable a souhait).
    d. EMAIL NOTIFICATION LOGISTICIEN (defaut :
       logistique@apc-ong.org  —  adresse qui recevra un email a
       CHAQUE nouvelle cotation fournisseur soumise sur le
       portail).
    e. DUREE VALIDITE TOKEN FOURNISSEUR : defaut 30 jours,
       min 7j, max 60j (recommandation : ne pas descendre sous 15j).
    f. PREFIXES DE NUMEROTATION : par defaut  EB-, REQ-, DP-,
       COT-, BC-, BR-, DAV-, JAV-, DPAI-  — adaptez si besoin.
    g. SEUIL STOCK CRITIQUE : defaut 1 (tout stock a 0 est rouge).

  Puis aller dans : Administration  ->  Utilisateurs  ->  Groupes
  — ou directement chaque utilisateur — onglet Permissions.
  Le module expose 5 droits (Lire / Creer / Modifier / Supprimer /
  Valider) pour CHACUN des 10 sous-modules (50 permissions
  au total).  Affectez-les selon la matrice de roles interne.

====================================================================
4 — CONTENU DE L'ARBORESCENCE
====================================================================

  apclogistics/
  ├─ core/modules/modApcLogistics.class.php   FICHIER PRINCIPAL
  │    (declaration module, constantes, menus, permissions)
  │
  ├─ apclogisticsindex.php    (redirection vers dashboard)
  ├─ dashboard.php            (tableau de bord + graphiques)
  │
  ├─ class/                   Classes metier + PDF
  │  ├─ Apc*.class.php        (10 entites + Audit + Token)
  │  └─ pdf/                  Generateurs PDF par document
  │
  ├─ admin/apclogistics_setup.php   (page config module)
  ├─ public/cotation.php            (PORTAIL FOURNISSEUR EXTERNE)
  ├─ scripts/                       (scripts test et maintenance)
  │
  ├─ css/apclogistics.css           Styles complementaires
  ├─ js/apclogistics.js             Helpers JS totaux ligne etc.
  ├─ img/
  │   ├─ apclogistics.png           Icone 32x32 menu Dolibarr
  │   └─ apc_logo_placeholder.png   Logo APC par defaut 300 DPI
  │
  ├─ vendor/chartjs/                Chart.js v4 local (NO CDN)
  │   └─ chart.umd.min.js
  │
  └─ langs/fr_FR.apclogistics.lang  Traduction Francaise
                          (structure extensible : en_EN, sw_SW...)

====================================================================
5 — MISE A JOUR
====================================================================

  1. Faire une SAUVEGARDE COMPLETE base + dossier apclogistics.
  2. DESACTIVER le module dans Admin > Modules (PAS le supprimer
     —  les tables llx_apclogistics_* seraient supprimees, sinon
     veillez bien a NE PAS COCHER "supprimer tables/constantes").
  3. Ecraser le dossier apclogistics/ par la nouvelle version.
  4. REACTIVER le module (Admin > Modules) : tables existantes
     sont preservees, seuls les nouveaux champs/constantes sont
     crees.
  5. Verifier Configuration module (valeurs preserved).

====================================================================
6 — DESINSTALLATION
====================================================================

  ⚠️ ATTENTION  —  action destructive !

    a. Administration -> Modules/Générateurs.
    b. En face de APC Logistique & Achats : cliquez DESACTIVER.
    c. Une case "Supprimer les tables et constantes du module"
       apparait. COCHEZ-LA UNIQUEMENT si vous voulez supprimer
       DEFINITIVEMENT les donnees APC (tous les Etats de besoin,
       commandes, mouvements de stock, avances etc.).
    d. Supprimez ensuite le dossier /htdocs/custom/apclogistics/
       via FTP.
    e. Videz le cache Dolibarr (Admin -> Divers).

====================================================================
7 — SUPPORT / CONTACT
====================================================================

  Equipe Logistique & Systemes d'Information APC ONG :
    📧 logistique@apc-ong.org   (assistance metier)
    📧 si@apc-ong.org           (assistance technique Dolibarr)

  Ce module est distribue sous licence GNU GPL v3. Vous pouvez
  l'etudier, le modifier et le redistribuer librement, a la
  condition de conserver cet en-tete de licence et de partager
  toute version modifiee sous la meme licence (copyleft).

     -- FIN DU README.txt --
