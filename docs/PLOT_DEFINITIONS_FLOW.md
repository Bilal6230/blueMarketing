# Plot Definitions — Project master data flow

## What the four groups mean
1. **Plot Units:** Define a reusable measurement unit, e.g. Marla or Square Feet. Create these first, because a Plot Size references one.
2. **Plot Sizes:** Define a reusable size, its unit, and a rate effective from a date. The rate is versioned in `plot_definition_rate_revisions`; changing the rate does not rewrite an existing plot's Plot Amount.
3. **Plot Types:** Define a project-specific label that maps to the system's existing **Residential (1)** or **Commercial (2)** value. No third legacy type can be introduced without changing the underlying model.
4. **Plot Categories:** Define optional descriptive groups of plots, e.g. Block or Category; link them when creating a plot.

## Typical sequence
1. Choose a project in the navbar.
2. Plot Definitions → add a **Unit**.
3. Add a **Size** referring to the unit; set its rate and effective date.
4. Add a **Type** mapped to Residential or Commercial and any Categories your project uses.
5. Plots List → Add Plot → enter unit/plot name and the legacy Residential/Commercial type. Select saved Category, Type, Size and Unit definitions in the separate **Saved Plot Definitions** section. Selecting Size fills the physical Size and Unit fields, and selecting Type Definition fills the legacy Type field.
6. Enter Plot Amount, Road, Front Facing, Corner, Status and optional notes, then Save.
7. The Plot list displays linked definition names where present. Existing plots without links still show legacy type/size/unit values.

## Important existing-data behavior
- Definitions and their rates are **project scoped**. This page can show zero records while the project already has plots; nothing is auto-backfilled.
- Creating or renaming a definition does **not** silently mutate existing plots, sold plots, or their amounts.
- Definition Code and legacy identity are immutable after creation; renaming requires a reason and is audited. Disable/enable requires a reason. New size rates are dated revisions with an audit trail.
- Plot create and update endpoints validate current-project definition IDs and cross-check selected type and size/unit compatibility.
- Treat definition rate as a reference master rate, not as an automatic replacement for `plots.amount` or old sales prices.

## UI regression test
`php artisan test --filter=PlotDefinitionsModernUiContractTest`

Also browser test the Create/Edit plot modals, Size→Unit auto-selection, project switching, form validation and existing legacy plots before acceptance.
