<?php

return [
    /*
     * Explicitly reviewed by the finance owner BEFORE enabling a charge.
     * Format:
     * 'extra_charge_accounts' => [
     *     PROJECT_ID => [
     *         CHARGE_TYPE_ID => [
     *             'approved' => true,
     *             'debit_account_id' => PROJECT_HEAD_SUBHEAD_ID,
     *             'credit_account_id' => PROJECT_HEAD_SUBHEAD_ID,
     *         ],
     *     ],
     * ],
     *
     * No default account inference. Cash mode only until the EC bank
     * instrument/clearance workflow is audited and implemented.
     */
    'extra_charge_accounts' => [],
];
