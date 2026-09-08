-- ============================================================================
--  SCHEMA SQL  —  Module APC Logistics & Procurement (Dolibarr custom)
--  Version       : 1.0.0
--  Auteur        : APC ONG Agri-Peace and Child
--  Licence       : GNU GPL v3
--  Compatible    : MySQL 5.7+ / MariaDB 10.5+ — collation utf8mb4_unicode_ci
--
--  NOTES CONVENTIONS :
--   - Toutes les tables prefixees par  llx_apclogistics_  (Dolibarr standard)
--   - Chaque table comporte :
--         rowid              INT AUTO_INCREMENT PRIMARY KEY
--         entity             INT DEFAULT 1         (multientite Dolibarr)
--         date_creation      DATETIME
--         tms                TIMESTAMP             (maj auto)
--         fk_user_creat      INT
--         fk_user_modif      INT
--   - Vrai/Faux : smallint(1)  DEFAULT 0
--   - Montants : double(24,8) ou decimal(24,8)
--   - References externes : llx_societe.rowid (fk_societe)
--                            llx_user.rowid    (fk_user_*)
--                            llx_product.rowid (fk_product)
--   - Tous les index secondaires prefixes par idx_apclog_
-- ============================================================================

-- -------------------------------------------------------------------------
-- 1) ETAT DE BESOIN
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_etatbesoin (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- EB-YYYY-NNNN
    ref_ext                    VARCHAR(128) DEFAULT NULL,
    date_eb                    DATE NOT NULL,
    objet                      VARCHAR(255) NOT NULL,

    total_ht                   DECIMAL(24,8) DEFAULT 0,
    total_ttc                  DECIMAL(24,8) DEFAULT 0,

    status                     SMALLINT NOT NULL DEFAULT 0,
    -- 0=brouillon, 1=attente validation, 2=valide, 9=annule
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    fk_user_demandeur          INT DEFAULT NULL,
    date_signature_demandeur   DATETIME DEFAULT NULL,
    signataire_nom_d           VARCHAR(128) DEFAULT NULL,
    signataire_fonction_d      VARCHAR(128) DEFAULT NULL,

    fk_user_verificateur       INT DEFAULT NULL,
    date_signature_verif       DATETIME DEFAULT NULL,
    signataire_nom_v           VARCHAR(128) DEFAULT NULL,
    signataire_fonction_v      VARCHAR(128) DEFAULT NULL,

    fk_user_approbateur        INT DEFAULT NULL,
    date_signature_approb      DATETIME DEFAULT NULL,
    signataire_nom_a           VARCHAR(128) DEFAULT NULL,
    signataire_fonction_a      VARCHAR(128) DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_eb_status       (status),
    KEY idx_apclog_eb_date         (date_eb),
    KEY idx_apclog_eb_demandeur    (fk_user_demandeur),
    KEY idx_apclog_eb_entity       (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_etatbesoin_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_etatbesoin              INT NOT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    depense                    VARCHAR(255) NOT NULL,
    projet_or_budget           VARCHAR(255) DEFAULT NULL,
    budget_code                VARCHAR(64)  DEFAULT NULL,
    compte                     VARCHAR(32)  DEFAULT NULL,
    montant                    DECIMAL(24,8) NOT NULL DEFAULT 0,

    fk_product                 INT DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_ebl_main    (fk_etatbesoin),
    KEY idx_apclog_ebl_prod    (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 2) REQUISITION / BON DE SORTIE MAGASIN
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_requisition (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- REQ-YYYY-NNNN
    ref_ext                    VARCHAR(128) DEFAULT NULL,
    date_demande               DATE NOT NULL,
    date_sortie                DATE DEFAULT NULL,

    objet                      VARCHAR(255) DEFAULT NULL,
    fk_etatbesoin              INT DEFAULT NULL,

    status                     SMALLINT NOT NULL DEFAULT 0,
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    fk_user_demandeur          INT DEFAULT NULL,
    demandeur_nom              VARCHAR(128) DEFAULT NULL,
    demandeur_fonction         VARCHAR(128) DEFAULT NULL,
    date_signature_demandeur   DATETIME DEFAULT NULL,

    fk_user_magasinier         INT DEFAULT NULL,
    magasinier_nom             VARCHAR(128) DEFAULT NULL,
    magasinier_fonction        VARCHAR(128) DEFAULT NULL,
    date_signature_magasinier  DATETIME DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_req_status      (status),
    KEY idx_apclog_req_datedem     (date_demande),
    KEY idx_apclog_req_eb          (fk_etatbesoin),
    KEY idx_apclog_req_entity      (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_requisition_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_requisition             INT NOT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    date_mouvement             DATE DEFAULT NULL,
    description                VARCHAR(255) NOT NULL,
    fk_product                 INT DEFAULT NULL,
    unite                      VARCHAR(32)  DEFAULT NULL,

    qte_demandee               DECIMAL(16,4) NOT NULL DEFAULT 0,
    qte_sortie                 DECIMAL(16,4) NOT NULL DEFAULT 0,
    ecart_qte                  DECIMAL(16,4) NOT NULL DEFAULT 0,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_reql_main   (fk_requisition),
    KEY idx_apclog_reql_prod   (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 3) DEMANDE DE PRIX
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_demandeprix (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- DP-YYYY-NNNN
    ref_ext                    VARCHAR(128) DEFAULT NULL,
    annee                      INT NOT NULL,
    date_dp                    DATE NOT NULL,

    fk_societe_cible           INT DEFAULT NULL,   -- societe Dolibarr
    fournisseur_nom            VARCHAR(255) DEFAULT NULL,
    fournisseur_adresse        TEXT DEFAULT NULL,
    fournisseur_tel            VARCHAR(32)  DEFAULT NULL,
    fournisseur_email          VARCHAR(128) DEFAULT NULL,
    fournisseur_contact        VARCHAR(128) DEFAULT NULL,

    apc_organisation           VARCHAR(255) DEFAULT 'APC ONG Agri-Peace and Child',
    apc_adresse                VARCHAR(255) DEFAULT NULL,
    apc_contact_nom            VARCHAR(128) DEFAULT NULL,

    lieu_livraison             VARCHAR(255) DEFAULT NULL,
    date_livraison             DATE DEFAULT NULL,

    status                     SMALLINT NOT NULL DEFAULT 0,
    -- 0=brouillon, 1=envoye, 2=cloturee, 9=annule
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    mention_legale             TEXT DEFAULT NULL,

    date_envoi                 DATETIME DEFAULT NULL,
    fk_user_envoi              INT DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_dp_status       (status),
    KEY idx_apclog_dp_date         (date_dp),
    KEY idx_apclog_dp_societe      (fk_societe_cible),
    KEY idx_apclog_dp_entity       (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_demandeprix_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_demandeprix             INT NOT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    specification              TEXT NOT NULL,
    unite                      VARCHAR(32)  DEFAULT NULL,
    quantite                   DECIMAL(16,4) NOT NULL DEFAULT 0,
    fk_product                 INT DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_dpl_main    (fk_demandeprix),
    KEY idx_apclog_dpl_prod    (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 4) TOKENS PORTAIL FOURNISSEUR
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_tokens (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_demandeprix             INT NOT NULL,

    token_hash                 VARCHAR(128) NOT NULL UNIQUE,   -- SHA-256 haché
    token_salt                 VARCHAR(64)  DEFAULT NULL,

    date_creation              DATETIME NOT NULL,
    date_expiration            DATETIME NOT NULL,
    date_utilisation           DATETIME DEFAULT NULL,

    used                       SMALLINT NOT NULL DEFAULT 0,   -- 0/1
    consumed_by_cotation_id    INT DEFAULT NULL,
    ip_used                    VARCHAR(45)  DEFAULT NULL,     -- IPv4/6
    user_agent                 VARCHAR(255) DEFAULT NULL,

    attempts_counter           INT NOT NULL DEFAULT 0,        -- rate limit

    date_creation_row          DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_tok_dp      (fk_demandeprix),
    KEY idx_apclog_tok_expire  (date_expiration, used),
    KEY idx_apclog_tok_entity  (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 5) COTATION / DEVIS FOURNISSEUR (reponse externe)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_cotation (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- COT-YYYY-NNNN
    fk_demandeprix             INT NOT NULL,
    fk_token                   INT DEFAULT NULL,   -- token portail utilise

    fournisseur_nom            VARCHAR(255) NOT NULL,
    fournisseur_adresse        TEXT DEFAULT NULL,
    fournisseur_tel            VARCHAR(32)  DEFAULT NULL,
    fournisseur_email          VARCHAR(128) DEFAULT NULL,
    fournisseur_contact        VARCHAR(128) DEFAULT NULL,
    fk_societe_match           INT DEFAULT NULL,   -- llx_societe.rowid apres comparaison
    fk_societe                 INT DEFAULT NULL,

    date_cotation              DATE NOT NULL,
    lieu_livraison             VARCHAR(255) DEFAULT NULL,
    date_livraison             DATE DEFAULT NULL,
    date_validite_offre        DATE DEFAULT NULL,
    delai_livraison_jours      INT DEFAULT NULL,
    delai_livraison            VARCHAR(128) DEFAULT NULL,
    conditions_paiement        VARCHAR(128) DEFAULT NULL,
    conditions_reglement       VARCHAR(128) DEFAULT NULL,
    conditions_acceptation     VARCHAR(8)   DEFAULT NULL,
    taux_tva_applicable        DECIMAL(6,3) DEFAULT 0,

    total_ht                   DECIMAL(24,8) NOT NULL DEFAULT 0,
    total_tva                  DECIMAL(24,8) NOT NULL DEFAULT 0,
    total_ttc                  DECIMAL(24,8) NOT NULL DEFAULT 0,
    devise                     VARCHAR(3)   DEFAULT 'CDF',

    conditions_checkbox        SMALLINT NOT NULL DEFAULT 0,   -- 0/1 case acceptee
    signature_nom              VARCHAR(128) DEFAULT NULL,
    signature_date             DATE DEFAULT NULL,
    signature_fournisseur_nom  VARCHAR(128) DEFAULT NULL,
    signature_fournisseur_fct  VARCHAR(128) DEFAULT NULL,
    signature_fournisseur_date DATE DEFAULT NULL,
    signature_fournisseur_ip   VARCHAR(45)  DEFAULT NULL,

    ecriture_date              DATETIME DEFAULT NULL,
    ip_soumission              VARCHAR(45)  DEFAULT NULL,
    user_agent_soumission      VARCHAR(255) DEFAULT NULL,

    status                     SMALLINT NOT NULL DEFAULT 0,
    -- 0=brouillon, 1=soumise, 2=selectionnee, 3=rejette, 9=annulee
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_cot_status     (status),
    KEY idx_apclog_cot_dp         (fk_demandeprix),
    KEY idx_apclog_cot_date       (date_cotation),
    KEY idx_apclog_cot_fksoc      (fk_societe_match),
    KEY idx_apclog_cot_entity     (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_cotation_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_cotation                INT NOT NULL,
    fk_demandeprix_line        INT DEFAULT NULL,   -- NULL si ligne saisie librement (card), renseigne via portail public
    no_ligne                   INT NOT NULL DEFAULT 0,

    fk_product                 INT DEFAULT NULL,
    description                VARCHAR(255) DEFAULT NULL,
    unite                      VARCHAR(32)  DEFAULT NULL,
    quantite                   DECIMAL(16,4) NOT NULL DEFAULT 0,
    prix_unitaire_ht           DECIMAL(24,8) NOT NULL DEFAULT 0,
    remise_pct                 DECIMAL(6,3) DEFAULT 0,
    total_ht                   DECIMAL(24,8) NOT NULL DEFAULT 0,
    prix_total_partiel         DECIMAL(24,8) NOT NULL DEFAULT 0,
    remarque                   VARCHAR(255) DEFAULT NULL,
    tva_tx                     DECIMAL(6,3) DEFAULT 0,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_cotl_main   (fk_cotation),
    KEY idx_apclog_cotl_dpl    (fk_demandeprix_line)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 6) BON DE COMMANDE
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_boncommande (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- BC-YYYY-NNNN
    ref_ext                    VARCHAR(128) DEFAULT NULL,
    date_cmde                  DATE NOT NULL,

    fk_cotation                INT DEFAULT NULL,
    fk_demandeprix             INT DEFAULT NULL,
    fk_societe                 INT DEFAULT NULL,

    fournisseur_nom            VARCHAR(255) DEFAULT NULL,
    fournisseur_adresse        TEXT DEFAULT NULL,
    fournisseur_tel            VARCHAR(32)  DEFAULT NULL,
    fournisseur_email          VARCHAR(128) DEFAULT NULL,
    fournisseur_contact        VARCHAR(128) DEFAULT NULL,

    lieu_livraison             VARCHAR(255) DEFAULT NULL,
    date_livraison             DATE DEFAULT NULL,
    conditions_paiement        VARCHAR(128) DEFAULT NULL,
    delai_reglement_jours      INT DEFAULT NULL,
    taux_tva_applicable        DECIMAL(6,3) DEFAULT 0,

    total_ht                   DECIMAL(24,8) NOT NULL DEFAULT 0,
    total_tva                  DECIMAL(24,8) NOT NULL DEFAULT 0,
    total_ttc                  DECIMAL(24,8) NOT NULL DEFAULT 0,
    devise                     VARCHAR(3)   DEFAULT 'CDF',

    status                     SMALLINT NOT NULL DEFAULT 0,
    -- 0=brouillon, 1=attente signature, 2=valide/envoye, 3=livre, 9=annule
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    fk_user_logisticien        INT DEFAULT NULL,
    logisticien_nom            VARCHAR(128) DEFAULT NULL,
    logisticien_fonction       VARCHAR(128) DEFAULT NULL,
    date_signature_log         DATETIME DEFAULT NULL,

    fk_user_coordinateur       INT DEFAULT NULL,
    coordinateur_nom           VARCHAR(128) DEFAULT NULL,
    coordinateur_fonction      VARCHAR(128) DEFAULT NULL,
    date_signature_coord       DATETIME DEFAULT NULL,

    fournisseur_sig_nom        VARCHAR(128) DEFAULT NULL,
    fournisseur_sig_fct        VARCHAR(128) DEFAULT NULL,
    fournisseur_sig_date       DATE DEFAULT NULL,
    fournisseur_sig_ip         VARCHAR(64)  DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_bc_status      (status),
    KEY idx_apclog_bc_date        (date_cmde),
    KEY idx_apclog_bc_cotation    (fk_cotation),
    KEY idx_apclog_bc_societe     (fk_societe),
    KEY idx_apclog_bc_entity      (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_boncommande_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_boncommande             INT NOT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    description                VARCHAR(255) NOT NULL,
    fk_product                 INT DEFAULT NULL,
    unite                      VARCHAR(32)  DEFAULT NULL,
    quantite                   DECIMAL(16,4) NOT NULL DEFAULT 0,
    prix_unitaire              DECIMAL(24,8) NOT NULL DEFAULT 0,
    prix_total_ligne           DECIMAL(24,8) NOT NULL DEFAULT 0,
    tva_tx                     DECIMAL(6,3) DEFAULT 0,
    qte_restante               DECIMAL(16,4) DEFAULT 0,
    fk_cotation_line           INT DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_bcl_main    (fk_boncommande),
    KEY idx_apclog_bcl_prod    (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 7) BON DE RECEPTION
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_bonreception (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- BR-YYYY-NNNN
    ref_ext                    VARCHAR(128) DEFAULT NULL,

    fk_boncommande             INT NOT NULL,
    fk_cotation                INT DEFAULT NULL,
    fk_societe                 INT DEFAULT NULL,

    fournisseur_nom            VARCHAR(255) DEFAULT NULL,
    ref_bdl                    VARCHAR(128) DEFAULT NULL,   -- Bon de livraison fourn
    ref_facture                VARCHAR(128) DEFAULT NULL,   -- Facture fournisseur
    date_reception             DATE NOT NULL,
    date_bl                    DATE DEFAULT NULL,          -- Date du bon de livraison fournisseur

    total_ht                   DECIMAL(24,8) NOT NULL DEFAULT 0,
    total_tva                  DECIMAL(24,8) NOT NULL DEFAULT 0,
    total_ttc                  DECIMAL(24,8) NOT NULL DEFAULT 0,
    devise                     VARCHAR(3)   DEFAULT 'CDF',

    total_qte_commandee        DECIMAL(16,4) NOT NULL DEFAULT 0,
    total_qte_recue            DECIMAL(16,4) NOT NULL DEFAULT 0,
    total_ecart                DECIMAL(16,4) NOT NULL DEFAULT 0,

    status                     SMALLINT NOT NULL DEFAULT 0,
    -- 0=brouillon, 1=valide, 2=integre stock, 9=annule
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    fk_user_receptionniste     INT DEFAULT NULL,
    reception_nom              VARCHAR(128) DEFAULT NULL,
    reception_fonction         VARCHAR(128) DEFAULT NULL,
    reception_date_sig         DATETIME DEFAULT NULL,

    fk_user_livreur            INT DEFAULT NULL,
    livraison_nom              VARCHAR(128) DEFAULT NULL,
    livraison_fonction         VARCHAR(128) DEFAULT NULL,
    livraison_date_sig         DATETIME DEFAULT NULL,
    livraison_cni              VARCHAR(64) DEFAULT NULL,   -- CNI livreur fournisseur

    stock_integre              SMALLINT NOT NULL DEFAULT 0,   -- 0/1
    date_integration_stock     DATETIME DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_br_status      (status),
    KEY idx_apclog_br_date        (date_reception),
    KEY idx_apclog_br_bc          (fk_boncommande),
    KEY idx_apclog_br_entity      (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_bonreception_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_bonreception            INT NOT NULL,
    fk_boncommande_line        INT DEFAULT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    description                VARCHAR(255) NOT NULL,
    fk_product                 INT DEFAULT NULL,
    unite                      VARCHAR(32)  DEFAULT NULL,

    qte_commandee              DECIMAL(16,4) NOT NULL DEFAULT 0,
    qte_recue                  DECIMAL(16,4) NOT NULL DEFAULT 0,
    ecart_qte                  DECIMAL(16,4) NOT NULL DEFAULT 0,

    prix_unitaire              DECIMAL(24,8) NOT NULL DEFAULT 0,
    prix_total_ligne           DECIMAL(24,8) NOT NULL DEFAULT 0,
    motif_ecart                VARCHAR(255) DEFAULT NULL,   -- Motif de l'écart de quantité

    stock_processed            SMALLINT NOT NULL DEFAULT 0,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_brl_main    (fk_bonreception),
    KEY idx_apclog_brl_bcl     (fk_boncommande_line),
    KEY idx_apclog_brl_prod    (fk_product)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 8) GESTION DES STOCKS (fiche + mouvements)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_stock (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_product                 INT NOT NULL UNIQUE,   -- 1 fiche par produit

    designation                VARCHAR(255) DEFAULT NULL,
    unite                      VARCHAR(32)  DEFAULT NULL,
    stock_actuel               DECIMAL(16,4) NOT NULL DEFAULT 0,
    seuil_alerte               DECIMAL(16,4) NOT NULL DEFAULT 1,
    emplacement                VARCHAR(128) DEFAULT NULL,

    fk_user_gestionnaire       INT DEFAULT NULL,
    fk_user_resp_logistique    INT DEFAULT NULL,

    last_movement_date         DATETIME DEFAULT NULL,
    last_stock_update          DATETIME DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_stock_prod    (fk_product),
    KEY idx_apclog_stock_alerte  (stock_actuel, seuil_alerte),
    KEY idx_apclog_stock_entity  (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_stock_movements (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_stock                   INT NOT NULL,
    fk_product                 INT NOT NULL,

    date_movement              DATE NOT NULL,

    ref_doc_type               VARCHAR(8) NOT NULL,
    -- 'BC' (bon commande entree), 'BR' (bon reception),
    -- 'REQ' (requisition sortie), 'INV' (inventaire), 'AJUST' (ajustement)
    ref_doc_id                 INT DEFAULT NULL,          -- rowid du document
    ref_doc_label              VARCHAR(64) DEFAULT NULL,   -- ref humaine ex: BC-2026-0001

    unite                      VARCHAR(32)  DEFAULT NULL,
    entree                     DECIMAL(16,4) NOT NULL DEFAULT 0,
    sortie                     DECIMAL(16,4) NOT NULL DEFAULT 0,
    stock_apres                DECIMAL(16,4) NOT NULL DEFAULT 0,

    motif                      VARCHAR(255) DEFAULT NULL,
    fk_user                    INT DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_stmv_stock   (fk_stock),
    KEY idx_apclog_stmv_prod    (fk_product),
    KEY idx_apclog_stmv_date    (date_movement),
    KEY idx_apclog_stmv_doc     (ref_doc_type, ref_doc_id),
    KEY idx_apclog_stmv_entity  (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 9) DEMANDE D'AVANCE
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_demandeavance (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- DAV-YYYY-NNNN
    ref_ext                    VARCHAR(128) DEFAULT NULL,
    date_dav                   DATE NOT NULL,

    objet                      VARCHAR(255) NOT NULL,
    compte                     VARCHAR(32)  DEFAULT NULL,

    mode_paiement              SMALLINT NOT NULL DEFAULT 1,  -- 1=caisse, 2=banque
    coord_banque_nom           VARCHAR(255) DEFAULT NULL,
    coord_banque_iban          VARCHAR(64)  DEFAULT NULL,
    coord_banque_banque        VARCHAR(128) DEFAULT NULL,
    coord_banque_swift         VARCHAR(16)  DEFAULT NULL,

    total_ht                   DECIMAL(24,8) NOT NULL DEFAULT 0,
    total                      DECIMAL(24,8) NOT NULL DEFAULT 0,
    devise                     VARCHAR(3)   DEFAULT 'CDF',

    status                     SMALLINT NOT NULL DEFAULT 0,
    -- 0=brouillon, 1=attente verif, 2=attente approb, 3=approuve/verse, 9=annule
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    fk_user_demandeur          INT DEFAULT NULL,
    demandeur_nom              VARCHAR(128) DEFAULT NULL,
    demandeur_fonction         VARCHAR(128) DEFAULT NULL,
    date_signature_demandeur   DATETIME DEFAULT NULL,

    fk_user_verificateur       INT DEFAULT NULL,
    verif_nom                  VARCHAR(128) DEFAULT NULL,
    verif_fonction             VARCHAR(128) DEFAULT NULL,
    date_signature_verif       DATETIME DEFAULT NULL,

    fk_user_approbateur        INT DEFAULT NULL,
    approb_nom                 VARCHAR(128) DEFAULT NULL,
    approb_fonction            VARCHAR(128) DEFAULT NULL,
    date_signature_approb      DATETIME DEFAULT NULL,

    date_decaissement          DATE DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_dav_status     (status),
    KEY idx_apclog_dav_date       (date_dav),
    KEY idx_apclog_dav_demandeur  (fk_user_demandeur),
    KEY idx_apclog_dav_entity     (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_demandeavance_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_demandeavance           INT NOT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    depense_label              VARCHAR(255) NOT NULL,
    projet                     VARCHAR(255) DEFAULT NULL,
    budget                     VARCHAR(128) DEFAULT NULL,
    montant                    DECIMAL(24,8) NOT NULL DEFAULT 0,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_davl_main   (fk_demandeavance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 10) JUSTIFICATION D'AVANCE
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_justifavance (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- JAV-YYYY-NNNN
    ref_ext                    VARCHAR(128) DEFAULT NULL,

    fk_demande_avance          INT NOT NULL,

    date_jav                   DATE NOT NULL,
    objet                      VARCHAR(255) DEFAULT NULL,

    total_depense              DECIMAL(24,8) NOT NULL DEFAULT 0,
    prise_avance               DECIMAL(24,8) NOT NULL DEFAULT 0,
    ecart                      DECIMAL(24,8) NOT NULL DEFAULT 0,
    sens_ecart                 SMALLINT NOT NULL DEFAULT 0,
    -- -1 = solde a rendre par le demandeur, +1 = du a APC, 0 = equilibre
    devise                     VARCHAR(3)   DEFAULT 'CDF',

    status                     SMALLINT NOT NULL DEFAULT 0,
    -- 0=brouillon, 1=attente, 2=approuve, 3=soude/solde, 9=annule
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    fk_user_auteur             INT DEFAULT NULL,
    justif_nom                 VARCHAR(128) DEFAULT NULL,
    justif_fonction            VARCHAR(128) DEFAULT NULL,
    date_signature_justif      DATETIME DEFAULT NULL,

    fk_user_verificateur       INT DEFAULT NULL,
    verif_nom                  VARCHAR(128) DEFAULT NULL,
    verif_fonction             VARCHAR(128) DEFAULT NULL,
    date_signature_verif       DATETIME DEFAULT NULL,

    fk_user_approbateur        INT DEFAULT NULL,
    approb_nom                 VARCHAR(128) DEFAULT NULL,
    approb_fonction            VARCHAR(128) DEFAULT NULL,
    date_signature_approb      DATETIME DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_jav_status     (status),
    KEY idx_apclog_jav_date       (date_jav),
    KEY idx_apclog_jav_linkdav    (fk_demande_avance),
    KEY idx_apclog_jav_entity     (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_justifavance_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_justifavance            INT NOT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    depense_label              VARCHAR(255) NOT NULL,
    projet                     VARCHAR(255) DEFAULT NULL,
    budget                     VARCHAR(128) DEFAULT NULL,
    compte                     VARCHAR(32)  DEFAULT NULL,
    montant                    DECIMAL(24,8) NOT NULL DEFAULT 0,

    date_facture               DATE DEFAULT NULL,
    ref_piece_justificative    VARCHAR(128) DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_javl_main   (fk_justifavance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 11) DEMANDE DE PAIEMENT
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_demandepaiement (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    ref                        VARCHAR(64)  NOT NULL UNIQUE,   -- DPAI-YYYY-NNNN
    ref_ext                    VARCHAR(128) DEFAULT NULL,
    date_dpai                  DATE NOT NULL,

    objet                      VARCHAR(255) NOT NULL,
    compte                     VARCHAR(32)  DEFAULT NULL,

    cas_usage                  SMALLINT NOT NULL DEFAULT 1,
    -- 1 = Avance activites / TDR
    -- 2 = Remboursement pieces justificatives
    -- 3 = Paiement factures fournisseurs

    mode_paiement              SMALLINT NOT NULL DEFAULT 1,   -- 1=caisse, 2=banque
    coord_banque_nom           VARCHAR(255) DEFAULT NULL,
    coord_banque_iban          VARCHAR(64)  DEFAULT NULL,
    coord_banque_banque        VARCHAR(128) DEFAULT NULL,
    coord_banque_swift         VARCHAR(16)  DEFAULT NULL,

    beneficiaire_nom           VARCHAR(255) DEFAULT NULL,
    beneficiaire_fk_societe    INT DEFAULT NULL,
    beneficiaire_fk_user       INT DEFAULT NULL,

    total                      DECIMAL(24,8) NOT NULL DEFAULT 0,
    devise                     VARCHAR(3)   DEFAULT 'CDF',

    status                     SMALLINT NOT NULL DEFAULT 0,
    -- 0=brouillon, 1=attente, 2=ordonne, 3=paye, 9=annule
    note_public                TEXT DEFAULT NULL,
    note_private               TEXT DEFAULT NULL,

    fk_user_demandeur          INT DEFAULT NULL,
    demandeur_nom              VARCHAR(128) DEFAULT NULL,
    demandeur_fonction         VARCHAR(128) DEFAULT NULL,
    date_signature_demandeur   DATETIME DEFAULT NULL,

    fk_user_verificateur       INT DEFAULT NULL,
    verif_nom                  VARCHAR(128) DEFAULT NULL,
    verif_fonction             VARCHAR(128) DEFAULT NULL,
    date_signature_verif       DATETIME DEFAULT NULL,

    fk_user_approbateur        INT DEFAULT NULL,
    approb_nom                 VARCHAR(128) DEFAULT NULL,
    approb_fonction            VARCHAR(128) DEFAULT NULL,
    date_signature_approb      DATETIME DEFAULT NULL,

    date_paiement_effectif     DATE DEFAULT NULL,

    extraparams                TEXT DEFAULT NULL,

    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_dpai_status    (status),
    KEY idx_apclog_dpai_date      (date_dpai),
    KEY idx_apclog_dpai_cas       (cas_usage),
    KEY idx_apclog_dpai_entity    (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS llx_apclogistics_demandepaiement_lines (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    fk_demandepaiement         INT NOT NULL,
    no_ligne                   INT NOT NULL DEFAULT 0,

    depense_label              VARCHAR(255) NOT NULL,
    projet                     VARCHAR(255) DEFAULT NULL,
    budget                     VARCHAR(128) DEFAULT NULL,
    montant                    DECIMAL(24,8) NOT NULL DEFAULT 0,

    extraparams                TEXT DEFAULT NULL,
    date_creation              DATETIME DEFAULT NULL,
    tms                        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    fk_user_creat              INT DEFAULT NULL,
    fk_user_modif              INT DEFAULT NULL,

    KEY idx_apclog_dpail_main  (fk_demandepaiement)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 12) AUDIT LOG GENERIQUE
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_auditlog (
    rowid                      BIGINT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    entity_type                VARCHAR(32)  NOT NULL,
    -- 'eb', 'req', 'dp', 'cot', 'bc', 'br', 'stock', 'stock_mv',
    -- 'dav', 'jav', 'dpai', 'token', 'setup'
    entity_id                  INT NOT NULL,

    action_type                VARCHAR(32)  NOT NULL,
    -- 'CREATE','UPDATE','DELETE','VALIDATE','SIGN','SEND','PRINT',
    -- 'LINK','UNLINK','CONSUME','LOGIN','SETUP','DOWNLOAD','EXPORT'

    action_details             TEXT DEFAULT NULL,

    old_values_json            MEDIUMTEXT DEFAULT NULL,
    new_values_json            MEDIUMTEXT DEFAULT NULL,

    fk_user                    INT DEFAULT NULL,
    user_login                 VARCHAR(50)  DEFAULT NULL,

    ip_address                 VARCHAR(45)  DEFAULT NULL,
    http_user_agent            VARCHAR(255) DEFAULT NULL,

    date_action                DATETIME NOT NULL,

    KEY idx_apclog_aud_entity    (entity_type, entity_id),
    KEY idx_apclog_aud_date      (date_action),
    KEY idx_apclog_aud_user      (fk_user),
    KEY idx_apclog_aud_action    (action_type),
    KEY idx_apclog_aud_entitycol (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -------------------------------------------------------------------------
-- 13) COMPTEURS DE NUMEROTATION ANNUELS
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS llx_apclogistics_numbering (
    rowid                      INT AUTO_INCREMENT PRIMARY KEY,
    entity                     INT DEFAULT 1 NOT NULL,

    doc_type                   VARCHAR(8)   NOT NULL,
    -- 'EB','REQ','DP','COT','BC','BR','DAV','JAV','DPAI'
    annee                      INT NOT NULL,
    last_number                INT NOT NULL DEFAULT 0,

    UNIQUE KEY uk_apclog_num (doc_type, annee, entity),
    KEY idx_apclog_num_entity  (entity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================================
-- FIN DU SCHEMA
-- ============================================================================
