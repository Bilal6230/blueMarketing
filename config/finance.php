<?php

return [
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
