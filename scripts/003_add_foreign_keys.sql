-- ============================================================================
--  CONTRAINTES FOREIGN KEY - Module APC Logistics & Procurement
--  Version       : 1.0.1
--  Auteur        : APC ONG Agri-Peace and Child
--  Licence       : GNU GPL v3
--  Compatible    : MySQL 5.7+ / MariaDB 10.5+
--
--  NOTES :
--   - Ce script ajoute les contraintes FOREIGN KEY manquantes pour garantir
--     l'intégrité référentielle des données
--   - Les contraintes utilisent ON DELETE CASCADE pour suppression en cascade
--   - À exécuter après le script 001_create_schema.sql
-- ============================================================================

-- -------------------------------------------------------------------------
-- 1) ETAT DE BESOIN
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_etatbesoin
ADD CONSTRAINT fk_apclog_eb_demandeur FOREIGN KEY (fk_user_demandeur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_eb_verificateur FOREIGN KEY (fk_user_verificateur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_eb_approbateur FOREIGN KEY (fk_user_approbateur) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_etatbesoin_lines
ADD CONSTRAINT fk_apclog_ebl_eb FOREIGN KEY (fk_etatbesoin) REFERENCES llx_apclogistics_etatbesoin(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_ebl_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- 2) REQUISITION / BON DE SORTIE MAGASIN
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_requisition
ADD CONSTRAINT fk_apclog_req_eb FOREIGN KEY (fk_etatbesoin) REFERENCES llx_apclogistics_etatbesoin(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_req_demandeur FOREIGN KEY (fk_user_demandeur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_req_magasinier FOREIGN KEY (fk_user_magasinier) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_requisition_lines
ADD CONSTRAINT fk_apclog_reql_req FOREIGN KEY (fk_requisition) REFERENCES llx_apclogistics_requisition(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_reql_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- 3) DEMANDE DE PRIX
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_demandeprix
ADD CONSTRAINT fk_apclog_dp_societe FOREIGN KEY (fk_societe_cible) REFERENCES llx_societe(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_dp_envoi FOREIGN KEY (fk_user_envoi) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_demandeprix_lines
ADD CONSTRAINT fk_apclog_dpl_dp FOREIGN KEY (fk_demandeprix) REFERENCES llx_apclogistics_demandeprix(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_dpl_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- 4) TOKENS PORTAIL FOURNISSEUR
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_tokens
ADD CONSTRAINT fk_apclog_tok_dp FOREIGN KEY (fk_demandeprix) REFERENCES llx_apclogistics_demandeprix(rowid) ON DELETE CASCADE;

-- -------------------------------------------------------------------------
-- 5) COTATION / DEVIS FOURNISSEUR
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_cotation
ADD CONSTRAINT fk_apclog_cot_dp FOREIGN KEY (fk_demandeprix) REFERENCES llx_apclogistics_demandeprix(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_cot_token FOREIGN KEY (fk_token) REFERENCES llx_apclogistics_tokens(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_cot_societe_match FOREIGN KEY (fk_societe_match) REFERENCES llx_societe(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_cot_societe FOREIGN KEY (fk_societe) REFERENCES llx_societe(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_cotation_lines
ADD CONSTRAINT fk_apclog_cotl_cot FOREIGN KEY (fk_cotation) REFERENCES llx_apclogistics_cotation(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_cotl_dpl FOREIGN KEY (fk_demandeprix_line) REFERENCES llx_apclogistics_demandeprix_lines(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_cotl_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- 6) BON DE COMMANDE
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_boncommande
ADD CONSTRAINT fk_apclog_bc_cotation FOREIGN KEY (fk_cotation) REFERENCES llx_apclogistics_cotation(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_bc_dp FOREIGN KEY (fk_demandeprix) REFERENCES llx_apclogistics_demandeprix(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_bc_societe FOREIGN KEY (fk_societe) REFERENCES llx_societe(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_bc_logisticien FOREIGN KEY (fk_user_logisticien) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_bc_coordinateur FOREIGN KEY (fk_user_coordinateur) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_boncommande_lines
ADD CONSTRAINT fk_apclog_bcl_bc FOREIGN KEY (fk_boncommande) REFERENCES llx_apclogistics_boncommande(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_bcl_cot FOREIGN KEY (fk_cotation_line) REFERENCES llx_apclogistics_cotation_lines(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_bcl_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- 7) BON DE RECEPTION
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_bonreception
ADD CONSTRAINT fk_apclog_br_bc FOREIGN KEY (fk_boncommande) REFERENCES llx_apclogistics_boncommande(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_br_cotation FOREIGN KEY (fk_cotation) REFERENCES llx_apclogistics_cotation(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_br_societe FOREIGN KEY (fk_societe) REFERENCES llx_societe(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_br_receptionniste FOREIGN KEY (fk_user_receptionniste) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_br_livreur FOREIGN KEY (fk_user_livreur) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_bonreception_lines
ADD CONSTRAINT fk_apclog_brl_br FOREIGN KEY (fk_bonreception) REFERENCES llx_apclogistics_bonreception(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_brl_bcl FOREIGN KEY (fk_boncommande_line) REFERENCES llx_apclogistics_boncommande_lines(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_brl_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- 8) GESTION DES STOCKS
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_stock
ADD CONSTRAINT fk_apclog_stock_product UNIQUE (fk_product),
ADD CONSTRAINT fk_apclog_stock_product_fk FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_stock_gestionnaire FOREIGN KEY (fk_user_gestionnaire) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_stock_resp_logistique FOREIGN KEY (fk_user_resp_logistique) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_stock_movements
ADD CONSTRAINT fk_apclog_stmv_stock FOREIGN KEY (fk_stock) REFERENCES llx_apclogistics_stock(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_stmv_product FOREIGN KEY (fk_product) REFERENCES llx_product(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_stmv_user FOREIGN KEY (fk_user) REFERENCES llx_user(rowid) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- 9) DEMANDE D'AVANCE
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_demandeavance
ADD CONSTRAINT fk_apclog_dav_demandeur FOREIGN KEY (fk_user_demandeur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_dav_verificateur FOREIGN KEY (fk_user_verificateur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_dav_approbateur FOREIGN KEY (fk_user_approbateur) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_demandeavance_lines
ADD CONSTRAINT fk_apclog_davl_dav FOREIGN KEY (fk_demandeavance) REFERENCES llx_apclogistics_demandeavance(rowid) ON DELETE CASCADE;

-- -------------------------------------------------------------------------
-- 10) JUSTIFICATION D'AVANCE
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_justifavance
ADD CONSTRAINT fk_apclog_jav_dav FOREIGN KEY (fk_demande_avance) REFERENCES llx_apclogistics_demandeavance(rowid) ON DELETE CASCADE,
ADD CONSTRAINT fk_apclog_jav_auteur FOREIGN KEY (fk_user_auteur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_jav_verificateur FOREIGN KEY (fk_user_verificateur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_jav_approbateur FOREIGN KEY (fk_user_approbateur) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_justifavance_lines
ADD CONSTRAINT fk_apclog_javl_jav FOREIGN KEY (fk_justifavance) REFERENCES llx_apclogistics_justifavance(rowid) ON DELETE CASCADE;

-- -------------------------------------------------------------------------
-- 11) DEMANDE DE PAIEMENT
-- -------------------------------------------------------------------------
ALTER TABLE llx_apclogistics_demandepaiement
ADD CONSTRAINT fk_apclog_dpai_beneficiaire_societe FOREIGN KEY (beneficiaire_fk_societe) REFERENCES llx_societe(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_dpai_beneficiaire_user FOREIGN KEY (beneficiaire_fk_user) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_dpai_demandeur FOREIGN KEY (fk_user_demandeur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_dpai_verificateur FOREIGN KEY (fk_user_verificateur) REFERENCES llx_user(rowid) ON DELETE SET NULL,
ADD CONSTRAINT fk_apclog_dpai_approbateur FOREIGN KEY (fk_user_approbateur) REFERENCES llx_user(rowid) ON DELETE SET NULL;

ALTER TABLE llx_apclogistics_demandepaiement_lines
ADD CONSTRAINT fk_apclog_dpail_dpai FOREIGN KEY (fk_demandepaiement) REFERENCES llx_apclogistics_demandepaiement(rowid) ON DELETE CASCADE;

-- ============================================================================
-- FIN DU SCRIPT
-- ============================================================================
