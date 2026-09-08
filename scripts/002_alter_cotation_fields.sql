-- ============================================================================
--  MIGRATION 002  —  Module APC Logistics & Procurement (Dolibarr custom)
--  But : ajouter les colonnes "famille code" manquantes aux tables COTATION
--        (utilisees par le portail public, la card, le PDF et BonCommande)
--  A executer UNE SEULE FOIS sur une base deja creee avec 001_create_schema.sql
--  Compatible : MySQL 5.7+ / MariaDB 10.5+
-- ============================================================================

-- Table llx_apclogistics_cotation
ALTER TABLE llx_apclogistics_cotation ADD COLUMN fk_token INT DEFAULT NULL AFTER fk_demandeprix;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN fk_societe INT DEFAULT NULL AFTER fk_societe_match;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN date_validite_offre DATE DEFAULT NULL AFTER date_livraison;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN delai_livraison VARCHAR(128) DEFAULT NULL AFTER delai_livraison_jours;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN conditions_reglement VARCHAR(128) DEFAULT NULL AFTER conditions_paiement;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN conditions_acceptation VARCHAR(8) DEFAULT NULL AFTER conditions_reglement;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN taux_tva_applicable DECIMAL(6,3) DEFAULT 0 AFTER conditions_acceptation;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN signature_fournisseur_nom VARCHAR(128) DEFAULT NULL AFTER signature_date;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN signature_fournisseur_fct VARCHAR(128) DEFAULT NULL AFTER signature_fournisseur_nom;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN signature_fournisseur_date DATE DEFAULT NULL AFTER signature_fournisseur_fct;
ALTER TABLE llx_apclogistics_cotation ADD COLUMN signature_fournisseur_ip VARCHAR(45) DEFAULT NULL AFTER signature_fournisseur_date;

-- Table llx_apclogistics_cotation_lines
ALTER TABLE llx_apclogistics_cotation_lines MODIFY COLUMN fk_demandeprix_line INT DEFAULT NULL;
ALTER TABLE llx_apclogistics_cotation_lines ADD COLUMN fk_product INT DEFAULT NULL AFTER no_ligne;
ALTER TABLE llx_apclogistics_cotation_lines ADD COLUMN description VARCHAR(255) DEFAULT NULL AFTER fk_product;
ALTER TABLE llx_apclogistics_cotation_lines ADD COLUMN unite VARCHAR(32) DEFAULT NULL AFTER description;
ALTER TABLE llx_apclogistics_cotation_lines ADD COLUMN quantite DECIMAL(16,4) NOT NULL DEFAULT 0 AFTER unite;
ALTER TABLE llx_apclogistics_cotation_lines ADD COLUMN remise_pct DECIMAL(6,3) DEFAULT 0 AFTER prix_unitaire_ht;
ALTER TABLE llx_apclogistics_cotation_lines ADD COLUMN total_ht DECIMAL(24,8) NOT NULL DEFAULT 0 AFTER remise_pct;

-- Table llx_apclogistics_justifavance (colonne objet utilisee par la card JAV)
ALTER TABLE llx_apclogistics_justifavance ADD COLUMN objet VARCHAR(255) DEFAULT NULL AFTER date_jav;

-- ============================================================================
--  FIN DE LA MIGRATION 002
-- ============================================================================