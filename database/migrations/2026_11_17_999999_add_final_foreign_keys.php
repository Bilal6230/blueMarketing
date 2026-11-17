<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Apply 250 source foreign keys after all 146 parent tables exist. */
return new class extends Migration {
    public function up(): void {
        DB::unprepared(<<<'BMFKEY0'
ALTER TABLE `accounting_expenses`
  ADD CONSTRAINT `v2_accounting_expenses_project_head_subheads_id_fk` FOREIGN KEY (`project_head_subheads_id`) REFERENCES `project_head_subheads` (`id`);
BMFKEY0);
        DB::unprepared(<<<'BMFKEY1'
ALTER TABLE `account_groups`
  ADD CONSTRAINT `account_groups_parent_project_fk` FOREIGN KEY (`parent_id`,`project_id`) REFERENCES `account_groups` (`id`, `project_id`),
  ADD CONSTRAINT `account_groups_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY1);
        DB::unprepared(<<<'BMFKEY2'
ALTER TABLE `account_group_audits`
  ADD CONSTRAINT `account_group_audit_scope_fk` FOREIGN KEY (`account_group_id`,`project_id`) REFERENCES `account_groups` (`id`, `project_id`),
  ADD CONSTRAINT `account_group_audits_head_accounting_id_foreign` FOREIGN KEY (`head_accounting_id`) REFERENCES `head_accountings` (`id`),
  ADD CONSTRAINT `account_group_audits_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY2);
        DB::unprepared(<<<'BMFKEY3'
ALTER TABLE `areas`
  ADD CONSTRAINT `v2_areas_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY3);
        DB::unprepared(<<<'BMFKEY4'
ALTER TABLE `attendance_devices`
  ADD CONSTRAINT `attendance_devices_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY4);
        DB::unprepared(<<<'BMFKEY5'
ALTER TABLE `attendance_device_user_mappings`
  ADD CONSTRAINT `attendance_device_user_mappings_attendance_device_id_foreign` FOREIGN KEY (`attendance_device_id`) REFERENCES `attendance_devices` (`id`),
  ADD CONSTRAINT `attendance_device_user_mappings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
BMFKEY5);
        DB::unprepared(<<<'BMFKEY6'
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `v2_bookings_broker_id_fk` FOREIGN KEY (`broker_id`) REFERENCES `subhead_accountings` (`id`),
  ADD CONSTRAINT `v2_bookings_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `v2_bookings_plot_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  ADD CONSTRAINT `v2_bookings_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY6);
        DB::unprepared(<<<'BMFKEY7'
ALTER TABLE `booking_details`
  ADD CONSTRAINT `booking_details_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`);
BMFKEY7);
        DB::unprepared(<<<'BMFKEY8'
ALTER TABLE `booking_edit_audits`
  ADD CONSTRAINT `v2_booking_edit_audits_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `v2_booking_edit_audits_journal_voucher_id_fk` FOREIGN KEY (`journal_voucher_id`) REFERENCES `journal_vouchers` (`id`),
  ADD CONSTRAINT `v2_booking_edit_audits_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `v2_booking_edit_audits_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
BMFKEY8);
        DB::unprepared(<<<'BMFKEY9'
ALTER TABLE `booking_vouchers`
  ADD CONSTRAINT `v2_booking_vouchers_bank_id_fk` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`),
  ADD CONSTRAINT `v2_booking_vouchers_booking_id_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `v2_booking_vouchers_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `v2_booking_vouchers_customer_ledger_id_fk` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`),
  ADD CONSTRAINT `v2_booking_vouchers_ledger_id_fk` FOREIGN KEY (`ledger_id`) REFERENCES `ledgers` (`id`),
  ADD CONSTRAINT `v2_booking_vouchers_plot_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  ADD CONSTRAINT `v2_booking_vouchers_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY9);
        DB::unprepared(<<<'BMFKEY10'
ALTER TABLE `commision_vouchers`
  ADD CONSTRAINT `commision_vouchers_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY10);
        DB::unprepared(<<<'BMFKEY11'
ALTER TABLE `commision_voucher_details`
  ADD CONSTRAINT `commision_voucher_details_commision_voucher_id_foreign` FOREIGN KEY (`commision_voucher_id`) REFERENCES `commision_vouchers` (`id`),
  ADD CONSTRAINT `v2_commision_voucher_details_account_id_fk` FOREIGN KEY (`account_id`) REFERENCES `project_head_subheads` (`id`);
BMFKEY11);
        DB::unprepared(<<<'BMFKEY12'
ALTER TABLE `contractors`
  ADD CONSTRAINT `contractors_account_project_fk` FOREIGN KEY (`project_head_subhead_id`,`project_id`) REFERENCES `project_head_subheads` (`id`, `project_id`),
  ADD CONSTRAINT `contractors_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY12);
        DB::unprepared(<<<'BMFKEY13'
ALTER TABLE `contractor_audits`
  ADD CONSTRAINT `contractor_audits_contractor_project_fk` FOREIGN KEY (`contractor_id`,`project_id`) REFERENCES `contractors` (`id`, `project_id`);
BMFKEY13);
        DB::unprepared(<<<'BMFKEY14'
ALTER TABLE `customer_ledger`
  ADD CONSTRAINT `customer_ledger_contractor_project_fk` FOREIGN KEY (`contractor_id`,`project_id`) REFERENCES `contractors` (`id`, `project_id`),
  ADD CONSTRAINT `customer_ledger_custom_method_scope_fk` FOREIGN KEY (`custom_payment_method_id`,`project_id`,`payment_type`) REFERENCES `project_custom_payment_methods` (`id`, `project_id`, `base_method_code`),
  ADD CONSTRAINT `customer_ledger_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `v2_customer_ledger_bank_id_fk` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`),
  ADD CONSTRAINT `v2_customer_ledger_plot_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  ADD CONSTRAINT `v2_customer_ledger_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY14);
        DB::unprepared(<<<'BMFKEY15'
ALTER TABLE `dasticash`
  ADD CONSTRAINT `dasticash_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY15);
        DB::unprepared(<<<'BMFKEY16'
ALTER TABLE `draft_ledgers`
  ADD CONSTRAINT `v2_draft_ledgers_bank_id_fk` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`),
  ADD CONSTRAINT `v2_draft_ledgers_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `v2_draft_ledgers_plot_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  ADD CONSTRAINT `v2_draft_ledgers_project_head_subheads_id_fk` FOREIGN KEY (`project_head_subheads_id`) REFERENCES `project_head_subheads` (`id`),
  ADD CONSTRAINT `v2_draft_ledgers_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY16);
        DB::unprepared(<<<'BMFKEY17'
ALTER TABLE `employee_attendance_audits`
  ADD CONSTRAINT `employee_att_audit_scope_fk` FOREIGN KEY (`employee_attendance_record_id`,`project_id`) REFERENCES `employee_attendance_records` (`id`, `project_id`);
BMFKEY17);
        DB::unprepared(<<<'BMFKEY18'
ALTER TABLE `employee_attendance_records`
  ADD CONSTRAINT `employee_att_profile_user_project_fk` FOREIGN KEY (`employee_profile_id`,`user_id`,`project_id`) REFERENCES `employee_profiles` (`id`, `user_id`, `project_id`),
  ADD CONSTRAINT `employee_att_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY18);
        DB::unprepared(<<<'BMFKEY19'
ALTER TABLE `employee_definitions`
  ADD CONSTRAINT `employee_definitions_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY19);
        DB::unprepared(<<<'BMFKEY20'
ALTER TABLE `employee_definition_audits`
  ADD CONSTRAINT `employee_definition_audit_scope_fk` FOREIGN KEY (`employee_definition_id`,`project_id`) REFERENCES `employee_definitions` (`id`, `project_id`);
BMFKEY20);
        DB::unprepared(<<<'BMFKEY21'
ALTER TABLE `employee_employment_revisions`
  ADD CONSTRAINT `employment_revisions_profile_project_fk` FOREIGN KEY (`employee_profile_id`,`project_id`) REFERENCES `employee_profiles` (`id`, `project_id`),
  ADD CONSTRAINT `employment_revisions_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY21);
        DB::unprepared(<<<'BMFKEY22'
ALTER TABLE `employee_payroll_payment_links`
  ADD CONSTRAINT `payroll_payment_item_fk` FOREIGN KEY (`payroll_run_item_id`) REFERENCES `employee_payroll_run_items` (`id`),
  ADD CONSTRAINT `payroll_payment_run_project_fk` FOREIGN KEY (`payroll_run_id`,`project_id`) REFERENCES `employee_payroll_runs` (`id`, `project_id`),
  ADD CONSTRAINT `payroll_payment_source_fk` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`);
BMFKEY22);
        DB::unprepared(<<<'BMFKEY23'
ALTER TABLE `employee_payroll_runs`
  ADD CONSTRAINT `employee_payroll_run_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY23);
        DB::unprepared(<<<'BMFKEY24'
ALTER TABLE `employee_payroll_run_audits`
  ADD CONSTRAINT `employee_payroll_audit_run_project_fk` FOREIGN KEY (`payroll_run_id`,`project_id`) REFERENCES `employee_payroll_runs` (`id`, `project_id`);
BMFKEY24);
        DB::unprepared(<<<'BMFKEY25'
ALTER TABLE `employee_payroll_run_items`
  ADD CONSTRAINT `employee_payroll_item_profile_project_fk` FOREIGN KEY (`employee_profile_id`,`project_id`) REFERENCES `employee_profiles` (`id`, `project_id`),
  ADD CONSTRAINT `employee_payroll_item_run_project_fk` FOREIGN KEY (`payroll_run_id`,`project_id`) REFERENCES `employee_payroll_runs` (`id`, `project_id`);
BMFKEY25);
        DB::unprepared(<<<'BMFKEY26'
ALTER TABLE `employee_profiles`
  ADD CONSTRAINT `department_definition_id_project_fk` FOREIGN KEY (`department_definition_id`,`project_id`) REFERENCES `employee_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `designation_definition_id_project_fk` FOREIGN KEY (`designation_definition_id`,`project_id`) REFERENCES `employee_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `employee_category_definition_id_project_fk` FOREIGN KEY (`employee_category_definition_id`,`project_id`) REFERENCES `employee_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `employee_profiles_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `employee_profiles_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
BMFKEY26);
        DB::unprepared(<<<'BMFKEY27'
ALTER TABLE `employee_profile_audits`
  ADD CONSTRAINT `employee_profile_audit_scope_fk` FOREIGN KEY (`employee_profile_id`,`project_id`) REFERENCES `employee_profiles` (`id`, `project_id`);
BMFKEY27);
        DB::unprepared(<<<'BMFKEY28'
ALTER TABLE `employee_tasks`
  ADD CONSTRAINT `employee_tasks_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `employee_tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `employee_tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY28);
        DB::unprepared(<<<'BMFKEY29'
ALTER TABLE `finance_bank_settlement_events`
  ADD CONSTRAINT `finance_bank_settlement_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `finance_bank_settlement_events_customer_ledger_id_foreign` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`),
  ADD CONSTRAINT `finance_bank_settlement_events_posting_batch_id_foreign` FOREIGN KEY (`posting_batch_id`) REFERENCES `finance_posting_batches` (`id`),
  ADD CONSTRAINT `finance_bank_settlement_events_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY29);
        DB::unprepared(<<<'BMFKEY30'
ALTER TABLE `finance_cheque_intakes`
  ADD CONSTRAINT `finance_cheque_intakes_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `finance_cheque_intakes_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY30);
        DB::unprepared(<<<'BMFKEY31'
ALTER TABLE `finance_cheque_intake_actions`
  ADD CONSTRAINT `fin_chq_action_intake_fk` FOREIGN KEY (`cheque_intake_id`) REFERENCES `finance_cheque_intakes` (`id`);
BMFKEY31);
        DB::unprepared(<<<'BMFKEY32'
ALTER TABLE `finance_cheque_voucher_claims`
  ADD CONSTRAINT `fin_cheque_claim_intake_fk` FOREIGN KEY (`cheque_intake_id`) REFERENCES `finance_cheque_intakes` (`id`);
BMFKEY32);
        DB::unprepared(<<<'BMFKEY33'
ALTER TABLE `finance_commission_line_sources`
  ADD CONSTRAINT `finance_commission_line_sources_posting_line_id_foreign` FOREIGN KEY (`posting_line_id`) REFERENCES `finance_posting_lines` (`id`),
  ADD CONSTRAINT `v2_cv_detail_source_fk` FOREIGN KEY (`commision_voucher_detail_id`) REFERENCES `commision_voucher_details` (`id`);
BMFKEY33);
        DB::unprepared(<<<'BMFKEY34'
ALTER TABLE `finance_extra_charge_payment_sources`
  ADD CONSTRAINT `ec_payment_charge_fk` FOREIGN KEY (`charge_customer_ledger_id`) REFERENCES `finance_extra_charge_sources` (`customer_ledger_id`),
  ADD CONSTRAINT `finance_extra_charge_payment_sources_customer_ledger_id_foreign` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`);
BMFKEY34);
        DB::unprepared(<<<'BMFKEY35'
ALTER TABLE `finance_extra_charge_sources`
  ADD CONSTRAINT `finance_extra_charge_sources_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `finance_extra_charge_sources_charge_type_id_foreign` FOREIGN KEY (`charge_type_id`) REFERENCES `charge_types` (`id`),
  ADD CONSTRAINT `finance_extra_charge_sources_customer_ledger_id_foreign` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`);
BMFKEY35);
        DB::unprepared(<<<'BMFKEY36'
ALTER TABLE `finance_journal_line_sources`
  ADD CONSTRAINT `finance_journal_line_sources_journal_voucher_detail_id_foreign` FOREIGN KEY (`journal_voucher_detail_id`) REFERENCES `journal_voucher_details` (`id`),
  ADD CONSTRAINT `finance_journal_line_sources_posting_line_id_foreign` FOREIGN KEY (`posting_line_id`) REFERENCES `finance_posting_lines` (`id`);
BMFKEY36);
        DB::unprepared(<<<'BMFKEY37'
ALTER TABLE `finance_labour_attendance_journals`
  ADD CONSTRAINT `finance_labour_attendance_journals_journal_voucher_id_foreign` FOREIGN KEY (`journal_voucher_id`) REFERENCES `journal_vouchers` (`id`),
  ADD CONSTRAINT `finance_labour_attendance_journals_labour_attendance_id_foreign` FOREIGN KEY (`labour_attendance_id`) REFERENCES `labour_attendances` (`id`);
BMFKEY37);
        DB::unprepared(<<<'BMFKEY38'
ALTER TABLE `finance_labour_payment_members`
  ADD CONSTRAINT `finance_labour_payment_members_customer_ledger_id_foreign` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`),
  ADD CONSTRAINT `finance_labour_payment_members_labour_ledger_id_foreign` FOREIGN KEY (`labour_ledger_id`) REFERENCES `labour_ledgers` (`id`);
BMFKEY38);
        DB::unprepared(<<<'BMFKEY39'
ALTER TABLE `finance_online_confirmation_events`
  ADD CONSTRAINT `fin_online_confirmation_intake_fk` FOREIGN KEY (`online_intake_id`) REFERENCES `finance_online_intakes` (`id`);
BMFKEY39);
        DB::unprepared(<<<'BMFKEY40'
ALTER TABLE `finance_online_intakes`
  ADD CONSTRAINT `finance_online_intakes_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY40);
        DB::unprepared(<<<'BMFKEY41'
ALTER TABLE `finance_online_voucher_claims`
  ADD CONSTRAINT `fin_online_claim_intake_fk` FOREIGN KEY (`online_intake_id`) REFERENCES `finance_online_intakes` (`id`);
BMFKEY41);
        DB::unprepared(<<<'BMFKEY42'
ALTER TABLE `finance_original_customer_requests`
  ADD CONSTRAINT `finance_original_customer_requests_customer_ledger_id_foreign` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`),
  ADD CONSTRAINT `finance_original_customer_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY42);
        DB::unprepared(<<<'BMFKEY43'
ALTER TABLE `finance_plot_purchases`
  ADD CONSTRAINT `finance_plot_purchases_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `finance_plot_purchases_seller_account_id_foreign` FOREIGN KEY (`seller_account_id`) REFERENCES `project_head_subheads` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `v2_plot_purch_creator_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
BMFKEY43);
        DB::unprepared(<<<'BMFKEY44'
ALTER TABLE `finance_plot_purchase_items`
  ADD CONSTRAINT `finance_plot_purchase_items_inventory_account_id_foreign` FOREIGN KEY (`inventory_account_id`) REFERENCES `project_head_subheads` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `finance_plot_purchase_items_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `finance_plot_purchases` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `v2_plot_purch_item_legacy_fk` FOREIGN KEY (`legacy_plot_id`) REFERENCES `plots` (`id`) ON DELETE RESTRICT;
BMFKEY44);
        DB::unprepared(<<<'BMFKEY45'
ALTER TABLE `finance_plot_purchase_postings`
  ADD CONSTRAINT `v2_plot_purch_post_batch_fk` FOREIGN KEY (`posting_batch_id`) REFERENCES `finance_posting_batches` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `v2_plot_purch_post_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`) ON DELETE RESTRICT,
  ADD CONSTRAINT `v2_plot_purch_post_src_fk` FOREIGN KEY (`source_id`) REFERENCES `finance_plot_purchases` (`id`) ON DELETE RESTRICT;
BMFKEY45);
        DB::unprepared(<<<'BMFKEY46'
ALTER TABLE `finance_plot_receipts`
  ADD CONSTRAINT `finance_plot_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `finance_plot_receipts_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `leads` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `finance_plot_receipts_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT;
BMFKEY46);
        DB::unprepared(<<<'BMFKEY47'
ALTER TABLE `finance_plot_receipt_allocations`
  ADD CONSTRAINT `finance_plot_receipt_allocations_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `finance_plot_receipt_allocations_plot_id_foreign` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `finance_plot_receipt_allocations_receipt_id_foreign` FOREIGN KEY (`receipt_id`) REFERENCES `finance_plot_receipts` (`id`) ON DELETE RESTRICT;
BMFKEY47);
        DB::unprepared(<<<'BMFKEY48'
ALTER TABLE `finance_plot_receipt_postings`
  ADD CONSTRAINT `finance_plot_receipt_postings_posting_batch_id_foreign` FOREIGN KEY (`posting_batch_id`) REFERENCES `finance_posting_batches` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `finance_plot_receipt_postings_source_id_foreign` FOREIGN KEY (`source_id`) REFERENCES `finance_plot_receipts` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `v2_plot_rcpt_post_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`) ON DELETE RESTRICT;
BMFKEY48);
        DB::unprepared(<<<'BMFKEY49'
ALTER TABLE `finance_posting_batches`
  ADD CONSTRAINT `finance_posting_batches_posted_by_foreign` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `finance_posting_batches_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `finance_posting_batches_reversal_of_batch_id_foreign` FOREIGN KEY (`reversal_of_batch_id`) REFERENCES `finance_posting_batches` (`id`);
BMFKEY49);
        DB::unprepared(<<<'BMFKEY50'
ALTER TABLE `finance_posting_booking_sources`
  ADD CONSTRAINT `finance_posting_booking_sources_booking_voucher_id_foreign` FOREIGN KEY (`booking_voucher_id`) REFERENCES `booking_vouchers` (`id`),
  ADD CONSTRAINT `v2_finance_posting_booking_sources_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`);
BMFKEY50);
        DB::unprepared(<<<'BMFKEY51'
ALTER TABLE `finance_posting_cash_sources`
  ADD CONSTRAINT `finance_posting_cash_sources_customer_ledger_id_foreign` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`),
  ADD CONSTRAINT `v2_finance_posting_cash_sources_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`);
BMFKEY51);
        DB::unprepared(<<<'BMFKEY52'
ALTER TABLE `finance_posting_commission_sources`
  ADD CONSTRAINT `finance_posting_commission_sources_commision_voucher_id_foreign` FOREIGN KEY (`commision_voucher_id`) REFERENCES `commision_vouchers` (`id`),
  ADD CONSTRAINT `v2_finance_posting_commission_sources_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`);
BMFKEY52);
        DB::unprepared(<<<'BMFKEY53'
ALTER TABLE `finance_posting_journal_sources`
  ADD CONSTRAINT `finance_posting_journal_sources_journal_voucher_id_foreign` FOREIGN KEY (`journal_voucher_id`) REFERENCES `journal_vouchers` (`id`),
  ADD CONSTRAINT `v2_finance_posting_journal_sources_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`);
BMFKEY53);
        DB::unprepared(<<<'BMFKEY54'
ALTER TABLE `finance_posting_labour_sources`
  ADD CONSTRAINT `finance_posting_labour_sources_labour_ledger_id_foreign` FOREIGN KEY (`labour_ledger_id`) REFERENCES `labour_ledgers` (`id`),
  ADD CONSTRAINT `v2_finance_posting_labour_sources_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`);
BMFKEY54);
        DB::unprepared(<<<'BMFKEY55'
ALTER TABLE `finance_posting_lines`
  ADD CONSTRAINT `finance_posting_lines_posting_batch_id_foreign` FOREIGN KEY (`posting_batch_id`) REFERENCES `finance_posting_batches` (`id`),
  ADD CONSTRAINT `finance_posting_lines_project_head_subhead_id_foreign` FOREIGN KEY (`project_head_subhead_id`) REFERENCES `project_head_subheads` (`id`);
BMFKEY55);
        DB::unprepared(<<<'BMFKEY56'
ALTER TABLE `finance_posting_source_claims`
  ADD CONSTRAINT `finance_posting_source_claims_posting_batch_id_foreign` FOREIGN KEY (`posting_batch_id`) REFERENCES `finance_posting_batches` (`id`);
BMFKEY56);
        DB::unprepared(<<<'BMFKEY57'
ALTER TABLE `finance_posting_stock_bill_sources`
  ADD CONSTRAINT `finance_posting_stock_bill_sources_stock_bill_id_foreign` FOREIGN KEY (`stock_bill_id`) REFERENCES `stock_bills` (`id`),
  ADD CONSTRAINT `v2_finance_posting_stock_bill_sources_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`);
BMFKEY57);
        DB::unprepared(<<<'BMFKEY58'
ALTER TABLE `finance_posting_stock_entry_sources`
  ADD CONSTRAINT `finance_posting_stock_entry_sources_stock_entry_id_foreign` FOREIGN KEY (`stock_entry_id`) REFERENCES `stock_entries` (`id`),
  ADD CONSTRAINT `v2_finance_posting_stock_entry_sources_claim_fk` FOREIGN KEY (`posting_batch_id`,`source_kind`) REFERENCES `finance_posting_source_claims` (`posting_batch_id`, `source_kind`);
BMFKEY58);
        DB::unprepared(<<<'BMFKEY59'
ALTER TABLE `finance_stock_bill_project_approvals`
  ADD CONSTRAINT `finance_stock_bill_project_approvals_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `finance_stock_bill_project_approvals_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `finance_stock_bill_project_approvals_stock_bill_id_foreign` FOREIGN KEY (`stock_bill_id`) REFERENCES `stock_bills` (`id`);
BMFKEY59);
        DB::unprepared(<<<'BMFKEY60'
ALTER TABLE `finance_stock_entry_project_approvals`
  ADD CONSTRAINT `finance_stock_entry_project_approvals_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `finance_stock_entry_project_approvals_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `finance_stock_entry_project_approvals_stock_entry_id_foreign` FOREIGN KEY (`stock_entry_id`) REFERENCES `stock_entries` (`id`);
BMFKEY60);
        DB::unprepared(<<<'BMFKEY61'
ALTER TABLE `hold_plots`
  ADD CONSTRAINT `hold_plots_plot_id_foreign` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`),
  ADD CONSTRAINT `hold_plots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
BMFKEY61);
        DB::unprepared(<<<'BMFKEY62'
ALTER TABLE `journal_vouchers`
  ADD CONSTRAINT `journal_vouchers_contractor_project_fk` FOREIGN KEY (`contractor_id`,`project_id`) REFERENCES `contractors` (`id`, `project_id`),
  ADD CONSTRAINT `v2_journal_vouchers_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY62);
        DB::unprepared(<<<'BMFKEY63'
ALTER TABLE `journal_voucher_details`
  ADD CONSTRAINT `journal_voucher_details_journal_voucher_id_foreign` FOREIGN KEY (`journal_voucher_id`) REFERENCES `journal_vouchers` (`id`),
  ADD CONSTRAINT `v2_journal_voucher_details_account_id_fk` FOREIGN KEY (`account_id`) REFERENCES `project_head_subheads` (`id`);
BMFKEY63);
        DB::unprepared(<<<'BMFKEY64'
ALTER TABLE `labours`
  ADD CONSTRAINT `laboure_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY64);
        DB::unprepared(<<<'BMFKEY65'
ALTER TABLE `labour_attendances`
  ADD CONSTRAINT `fk_labour_attendances_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `labour_attendances_labour_id_foreign` FOREIGN KEY (`labour_id`) REFERENCES `labours` (`id`),
  ADD CONSTRAINT `labour_attendances_marked_by_foreign` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `labour_attendances_site_id_foreign` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`);
BMFKEY65);
        DB::unprepared(<<<'BMFKEY66'
ALTER TABLE `labour_ledgers`
  ADD CONSTRAINT `fk_labour_ledgers_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `labour_ledgers_contractor_project_fk` FOREIGN KEY (`contractor_id`,`project_id`) REFERENCES `contractors` (`id`, `project_id`),
  ADD CONSTRAINT `labour_ledgers_labour_project_fk` FOREIGN KEY (`labour_id`,`project_id`) REFERENCES `labours` (`id`, `project_id`),
  ADD CONSTRAINT `labour_ledgers_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY66);
        DB::unprepared(<<<'BMFKEY67'
ALTER TABLE `leads`
  ADD CONSTRAINT `v2_leads_area_id_fk` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  ADD CONSTRAINT `v2_leads_follow_id_fk` FOREIGN KEY (`follow_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `v2_leads_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `v2_leads_zone_id_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`);
BMFKEY67);
        DB::unprepared(<<<'BMFKEY68'
ALTER TABLE `lead_user`
  ADD CONSTRAINT `lead_user_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `lead_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
BMFKEY68);
        DB::unprepared(<<<'BMFKEY69'
ALTER TABLE `ledgers`
  ADD CONSTRAINT `fk_ledgers_charge_type` FOREIGN KEY (`charge_type_id`) REFERENCES `charge_types` (`id`),
  ADD CONSTRAINT `fk_ledgers_customer_ledger` FOREIGN KEY (`customer_ledger_id`) REFERENCES `customer_ledger` (`id`),
  ADD CONSTRAINT `ledgers_jv_detail_fk` FOREIGN KEY (`journal_voucher_detail_id`) REFERENCES `journal_voucher_details` (`id`),
  ADD CONSTRAINT `v2_ledgers_project_head_subheads_id_fk` FOREIGN KEY (`project_head_subheads_id`) REFERENCES `project_head_subheads` (`id`);
BMFKEY69);
        DB::unprepared(<<<'BMFKEY70'
ALTER TABLE `ledger_voucher_counters`
  ADD CONSTRAINT `ledger_voucher_counter_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY70);
        DB::unprepared(<<<'BMFKEY71'
ALTER TABLE `model_has_permissions`
  ADD CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`);
BMFKEY71);
        DB::unprepared(<<<'BMFKEY72'
ALTER TABLE `model_has_roles`
  ADD CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
BMFKEY72);
        DB::unprepared(<<<'BMFKEY73'
ALTER TABLE `plots`
  ADD CONSTRAINT `plot_category_definition_id_project_fk` FOREIGN KEY (`plot_category_definition_id`,`project_id`) REFERENCES `plot_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `plot_size_definition_id_project_fk` FOREIGN KEY (`plot_size_definition_id`,`project_id`) REFERENCES `plot_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `plot_type_definition_id_project_fk` FOREIGN KEY (`plot_type_definition_id`,`project_id`) REFERENCES `plot_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `plot_unit_definition_id_project_fk` FOREIGN KEY (`plot_unit_definition_id`,`project_id`) REFERENCES `plot_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `v2_plots_facing_id_fk` FOREIGN KEY (`facing_id`) REFERENCES `facings` (`id`),
  ADD CONSTRAINT `v2_plots_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `v2_plots_road_id_fk` FOREIGN KEY (`road_id`) REFERENCES `roads` (`id`);
BMFKEY73);
        DB::unprepared(<<<'BMFKEY74'
ALTER TABLE `plot_definitions`
  ADD CONSTRAINT `plot_definitions_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `plot_definitions_unit_project_fk` FOREIGN KEY (`unit_definition_id`,`project_id`) REFERENCES `plot_definitions` (`id`, `project_id`);
BMFKEY74);
        DB::unprepared(<<<'BMFKEY75'
ALTER TABLE `plot_definition_audits`
  ADD CONSTRAINT `plot_definition_audit_definition_project_fk` FOREIGN KEY (`plot_definition_id`,`project_id`) REFERENCES `plot_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `plot_definition_audit_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY75);
        DB::unprepared(<<<'BMFKEY76'
ALTER TABLE `plot_definition_rate_revisions`
  ADD CONSTRAINT `plot_definition_rate_definition_project_fk` FOREIGN KEY (`plot_definition_id`,`project_id`) REFERENCES `plot_definitions` (`id`, `project_id`),
  ADD CONSTRAINT `plot_definition_rate_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY76);
        DB::unprepared(<<<'BMFKEY77'
ALTER TABLE `project_bank_accounts`
  ADD CONSTRAINT `project_bank_account_mapping_project_fk` FOREIGN KEY (`account_mapping_id`,`project_id`) REFERENCES `project_head_subheads` (`id`, `project_id`),
  ADD CONSTRAINT `project_bank_account_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY77);
        DB::unprepared(<<<'BMFKEY78'
ALTER TABLE `project_bank_account_audits`
  ADD CONSTRAINT `project_bank_account_audit_scope_fk` FOREIGN KEY (`bank_account_id`,`project_id`) REFERENCES `project_bank_accounts` (`id`, `project_id`);
BMFKEY78);
        DB::unprepared(<<<'BMFKEY79'
ALTER TABLE `project_charge_revisions`
  ADD CONSTRAINT `project_charge_revisions_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY79);
        DB::unprepared(<<<'BMFKEY80'
ALTER TABLE `project_custom_payment_methods`
  ADD CONSTRAINT `project_custom_payment_methods_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY80);
        DB::unprepared(<<<'BMFKEY81'
ALTER TABLE `project_custom_payment_method_audits`
  ADD CONSTRAINT `project_custom_payment_method_audits_method_id_foreign` FOREIGN KEY (`method_id`) REFERENCES `project_custom_payment_methods` (`id`),
  ADD CONSTRAINT `project_custom_payment_method_audits_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY81);
        DB::unprepared(<<<'BMFKEY82'
ALTER TABLE `project_head_account_groups`
  ADD CONSTRAINT `project_head_account_groups_head_accounting_id_foreign` FOREIGN KEY (`head_accounting_id`) REFERENCES `head_accountings` (`id`),
  ADD CONSTRAINT `project_head_account_groups_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_head_group_scope_fk` FOREIGN KEY (`account_group_id`,`project_id`) REFERENCES `account_groups` (`id`, `project_id`);
BMFKEY82);
        DB::unprepared(<<<'BMFKEY83'
ALTER TABLE `project_head_subheads`
  ADD CONSTRAINT `project_head_subheads_head_accounting_id_foreign` FOREIGN KEY (`head_accounting_id`) REFERENCES `head_accountings` (`id`),
  ADD CONSTRAINT `project_head_subheads_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_head_subheads_subhead_accounting_id_foreign` FOREIGN KEY (`subhead_accounting_id`) REFERENCES `subhead_accountings` (`id`),
  ADD CONSTRAINT `v2_project_head_subheads_customer_id_fk` FOREIGN KEY (`customer_id`) REFERENCES `leads` (`id`),
  ADD CONSTRAINT `v2_project_head_subheads_plot_id_fk` FOREIGN KEY (`plot_id`) REFERENCES `plots` (`id`);
BMFKEY83);
        DB::unprepared(<<<'BMFKEY84'
ALTER TABLE `project_payment_method_labels`
  ADD CONSTRAINT `project_payment_method_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY84);
        DB::unprepared(<<<'BMFKEY85'
ALTER TABLE `project_payment_method_label_audits`
  ADD CONSTRAINT `project_payment_method_audit_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY85);
        DB::unprepared(<<<'BMFKEY86'
ALTER TABLE `project_voucher_display_numbers`
  ADD CONSTRAINT `project_voucher_display_numbers_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY86);
        DB::unprepared(<<<'BMFKEY87'
ALTER TABLE `project_voucher_display_settings`
  ADD CONSTRAINT `project_voucher_display_settings_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY87);
        DB::unprepared(<<<'BMFKEY88'
ALTER TABLE `project_voucher_display_setting_audits`
  ADD CONSTRAINT `project_voucher_display_setting_audits_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY88);
        DB::unprepared(<<<'BMFKEY89'
ALTER TABLE `project_voucher_fiscal_sequences`
  ADD CONSTRAINT `project_voucher_fiscal_sequences_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY89);
        DB::unprepared(<<<'BMFKEY90'
ALTER TABLE `project_zones`
  ADD CONSTRAINT `project_zones_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `project_zones_zone_id_foreign` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`);
BMFKEY90);
        DB::unprepared(<<<'BMFKEY91'
ALTER TABLE `punches`
  ADD CONSTRAINT `v2_punches_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `v2_punches_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
BMFKEY91);
        DB::unprepared(<<<'BMFKEY92'
ALTER TABLE `role_has_permissions`
  ADD CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  ADD CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
BMFKEY92);
        DB::unprepared(<<<'BMFKEY93'
ALTER TABLE `sites`
  ADD CONSTRAINT `fk_sites_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `v2_site_project_account_fk` FOREIGN KEY (`project_head_subhead_id`) REFERENCES `project_head_subheads` (`id`),
  ADD CONSTRAINT `v2_sites_head_accounting_id_fk` FOREIGN KEY (`head_accounting_id`) REFERENCES `head_accountings` (`id`),
  ADD CONSTRAINT `v2_sites_subhead_accounting_id_fk` FOREIGN KEY (`subhead_accounting_id`) REFERENCES `project_head_subheads` (`id`);
BMFKEY93);
        DB::unprepared(<<<'BMFKEY94'
ALTER TABLE `stocks`
  ADD CONSTRAINT `stocks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY94);
        DB::unprepared(<<<'BMFKEY95'
ALTER TABLE `stock_bills`
  ADD CONSTRAINT `stock_bills_entry_project_fk` FOREIGN KEY (`entry_id`,`project_id`) REFERENCES `stock_entries` (`id`, `project_id`),
  ADD CONSTRAINT `stock_bills_party_project_fk` FOREIGN KEY (`project_id`,`party_id`) REFERENCES `stock_party_project_accounts` (`project_id`, `stock_party_id`),
  ADD CONSTRAINT `stock_bills_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  ADD CONSTRAINT `v2_stock_bills_entry_id_fk` FOREIGN KEY (`entry_id`) REFERENCES `stock_entries` (`id`),
  ADD CONSTRAINT `v2_stock_bills_party_id_fk` FOREIGN KEY (`party_id`) REFERENCES `stock_parties` (`id`);
BMFKEY95);
        DB::unprepared(<<<'BMFKEY96'
ALTER TABLE `stock_bill_lines`
  ADD CONSTRAINT `stock_bill_lines_bill_project_fk` FOREIGN KEY (`bill_id`,`project_id`) REFERENCES `stock_bills` (`id`, `project_id`),
  ADD CONSTRAINT `stock_bill_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`),
  ADD CONSTRAINT `stock_bill_lines_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY96);
        DB::unprepared(<<<'BMFKEY97'
ALTER TABLE `stock_entries`
  ADD CONSTRAINT `stock_entries_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `stock_parties` (`id`),
  ADD CONSTRAINT `stock_entries_party_project_fk` FOREIGN KEY (`project_id`,`party_id`) REFERENCES `stock_party_project_accounts` (`project_id`, `stock_party_id`),
  ADD CONSTRAINT `stock_entries_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY97);
        DB::unprepared(<<<'BMFKEY98'
ALTER TABLE `stock_entry_lines`
  ADD CONSTRAINT `stock_entry_lines_entry_project_fk` FOREIGN KEY (`entry_id`,`project_id`) REFERENCES `stock_entries` (`id`, `project_id`),
  ADD CONSTRAINT `stock_entry_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`),
  ADD CONSTRAINT `stock_entry_lines_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY98);
        DB::unprepared(<<<'BMFKEY99'
ALTER TABLE `stock_item_balances`
  ADD CONSTRAINT `stock_item_balances_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`);
BMFKEY99);
        DB::unprepared(<<<'BMFKEY100'
ALTER TABLE `stock_item_import_batches`
  ADD CONSTRAINT `stock_item_import_batches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_item_import_batches_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY100);
        DB::unprepared(<<<'BMFKEY101'
ALTER TABLE `stock_item_import_rows`
  ADD CONSTRAINT `stock_item_import_rows_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `stock_item_import_batches` (`id`),
  ADD CONSTRAINT `stock_item_import_rows_stock_item_id_foreign` FOREIGN KEY (`stock_item_id`) REFERENCES `stock_items` (`id`);
BMFKEY101);
        DB::unprepared(<<<'BMFKEY102'
ALTER TABLE `stock_legacy_reconciliation_audits`
  ADD CONSTRAINT `stock_legacy_reconcile_actor_fk` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_legacy_reconcile_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY102);
        DB::unprepared(<<<'BMFKEY103'
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`),
  ADD CONSTRAINT `stock_movements_party_id_foreign` FOREIGN KEY (`party_id`) REFERENCES `stock_parties` (`id`),
  ADD CONSTRAINT `stock_movements_party_project_fk` FOREIGN KEY (`project_id`,`party_id`) REFERENCES `stock_party_project_accounts` (`project_id`, `stock_party_id`),
  ADD CONSTRAINT `stock_movements_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY103);
        DB::unprepared(<<<'BMFKEY104'
ALTER TABLE `stock_party_project_accounts`
  ADD CONSTRAINT `stock_party_project_account_account_fk` FOREIGN KEY (`project_head_subhead_id`,`project_id`) REFERENCES `project_head_subheads` (`id`, `project_id`),
  ADD CONSTRAINT `stock_party_project_account_party_fk` FOREIGN KEY (`stock_party_id`) REFERENCES `stock_parties` (`id`),
  ADD CONSTRAINT `stock_party_project_account_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY104);
        DB::unprepared(<<<'BMFKEY105'
ALTER TABLE `stock_party_project_account_audits`
  ADD CONSTRAINT `stock_party_account_audit_new_fk` FOREIGN KEY (`new_account_id`,`project_id`) REFERENCES `project_head_subheads` (`id`, `project_id`),
  ADD CONSTRAINT `stock_party_account_audit_old_fk` FOREIGN KEY (`previous_account_id`,`project_id`) REFERENCES `project_head_subheads` (`id`, `project_id`),
  ADD CONSTRAINT `stock_party_account_audit_party_fk` FOREIGN KEY (`stock_party_id`) REFERENCES `stock_parties` (`id`),
  ADD CONSTRAINT `stock_party_account_audit_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY105);
        DB::unprepared(<<<'BMFKEY106'
ALTER TABLE `stock_project_item_balances`
  ADD CONSTRAINT `stock_project_balance_item_fk` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`),
  ADD CONSTRAINT `stock_project_balance_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY106);
        DB::unprepared(<<<'BMFKEY107'
ALTER TABLE `stock_project_item_definitions`
  ADD CONSTRAINT `stock_project_item_definition_item_fk` FOREIGN KEY (`item_id`) REFERENCES `stock_items` (`id`),
  ADD CONSTRAINT `stock_project_item_definition_project_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY107);
        DB::unprepared(<<<'BMFKEY108'
ALTER TABLE `users`
  ADD CONSTRAINT `v2_users_project_id_fk` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`);
BMFKEY108);
        DB::unprepared(<<<'BMFKEY109'
ALTER TABLE `works`
  ADD CONSTRAINT `v2_works_user_id_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `works_lead_id_foreign` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`);
BMFKEY109);
    }
    public function down(): void { throw new LogicException('Review dependent tables before rollback of schema foreign keys.'); }
