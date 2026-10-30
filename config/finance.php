<?php

return [
    // V2 pilot routes are NOT registered by default, ever in production.
    // Enabling is limited to an isolated local/testing DB and an explicit
    // authenticated, project-authorized API session.
    'v2_pilot_api_enabled' => env('V2_FINANCE_PILOT_API_ENABLED', false),
    'v2_pilot_write_enabled' => env('V2_FINANCE_PILOT_WRITE_ENABLED', false),

    /*
     * Extra Charge project accounts are resolved automatically inside the
     * selected project's posting transaction:
     *   Extra Charge / Extra Charge Income
     *   Extra Charge / Extra Charge Receivable (customer/plot scoped)
     *   Extra Charge / Extra Charge Cash
     *   Extra Charge / Extra Charge Bank
     *
     * No manual IDs are required. Existing project accounts with these exact
     * names are reused. Other project cash/commission or bank heads are never
     * silently repurposed by this Extra Charge workflow.
     *
     * IMPORTANT: These are dedicated bookkeeping subheads; actual external
     * bank instrument identifiers and treasury transfer/reconciliation remain
     * separate from ledger account auto-provisioning.
     */
];
