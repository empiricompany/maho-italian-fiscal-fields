# Maho Italian Fiscal Fields

![Maho Commerce](https://img.shields.io/badge/Maho_Commerce-module-orange)
![License](https://img.shields.io/badge/license-OSL--3.0-blue)
![PHP](https://img.shields.io/badge/php-%3E%3D8.3-8892BF)
![PHPStan Level](https://img.shields.io/badge/PHPStan-level%208-brightgreen)

**Italian fiscal fields for Maho Commerce addresses and checkout.** Adds a private/company customer type selector together with Codice Fiscale, PEC email and SdI code to the customer address book and the checkout billing form, with country-aware visibility, required-field marking and format validation. Built for Italian stores that need electronic invoicing data, for [Maho Commerce](https://mahocommerce.com).

## What it does

- Adds a **Customer Type** select (Private / Company) right after the country field of the customer address form and the checkout billing form, and groups the existing Company and VAT Number fields below it
- **Codice Fiscale**, **Email PEC** and **Codice SdI** fields are injected dynamically — no template overrides, works with any theme
- Visibility is country- and type-aware: company name and VAT number only for companies, Codice Fiscale for companies in Italy (and optionally for private customers), PEC and SdI only for companies in Italy
- Required fields are marked with the red asterisk consistently across the whole form, including the checkout's own fields
- Validation: EU VAT number format, Codice Fiscale format, SdI code (7 alphanumeric chars), PEC email format, and at least one between PEC and SdI for electronic invoicing
- Ships with Italian and English translations (the JS strings are registered through `jstranslator.xml`, so they follow the store locale)
- Saved values are carried over to orders, order addresses and back to the customer address book

## Requirements

- PHP >= 8.3
- Maho Commerce

## Installation

```bash
composer require empiricompany/maho-italian-fiscal-fields
```

## The VAT number field: `vat_id`, not `taxvat`

The module is designed around the **`vat_id` address attribute** — the VAT number that belongs to a customer *address* — and not the `taxvat` attribute, which belongs to the customer *account*. The customer type selector decides "company" based on the address's `vat_id`, the field is grouped with the other fiscal fields, and the address templates render it.

To have the VAT number field on the frontend forms, enable it once:

1. Go to System → Configuration → Customers → Customer Configuration → **Create New Account Options**
2. Set **Show VAT Number on Frontend** to **Yes** (website scope)

Once enabled, the module automatically relocates the existing `vat_id` input right below the Customer Type select, together with the other fiscal fields — no template change needed. If the setting is left on No, the field only appears in the admin.

## Configuration

System → Configuration → Customers → Customer Configuration → **Customer Address Fiscal Fields**:

- **Show Codice Fiscale for Private customers** — when enabled, the Codice Fiscale field is also shown (and required) for private customers in Italy
- **Default Customer Type (new address)** — the type preselected for new addresses; existing addresses use their saved VAT ID to decide (company if a VAT ID is present)

### Address templates

The module ships extended defaults for the five address templates (Text, Text One Line, HTML, PDF, JavaScript Template) so the fiscal fields appear wherever an address is rendered — customer account, checkout, order emails and PDF invoices. Each field is wrapped in `{{depend ...}}` so it only shows up when filled.

**Important for existing stores:** address templates are stored in the database as soon as they are saved once from the admin, and database values always take precedence over the defaults in `config.xml`. If your store already has saved templates, the module defaults will NOT apply automatically. Check System → Configuration → Customers → Customer Configuration → **Address Templates** and replace your saved templates with the ones below (or just add the fiscal `{{depend}}` blocks to your own customizations).

If you never touched the Address Templates page, or you want to restore the module defaults, delete the `customer/address_templates/*` rows from `core_config_data` (or reset the fields to their default in the admin) and the extended templates will take effect.

<details>
<summary><strong>Text</strong> (click to expand)</summary>

```
{{depend prefix}}{{var prefix}} {{/depend}}{{var firstname}} {{depend middlename}}{{var middlename}} {{/depend}}{{var lastname}}{{depend suffix}} {{var suffix}}{{/depend}}
{{depend company}}{{var company}}{{/depend}}
{{if street1}}{{var street1}}
{{/if}}
{{depend street2}}{{var street2}}{{/depend}}
{{depend street3}}{{var street3}}{{/depend}}
{{depend street4}}{{var street4}}{{/depend}}
{{if city}}{{var city}},  {{/if}}{{if region}}{{var region}}, {{/if}}{{if postcode}}{{var postcode}}{{/if}}
{{var country}}
T: {{var telephone}}
{{depend vat_id}}VAT: {{var vat_id}}{{/depend}}
{{depend cod_fisc}}CF: {{var cod_fisc}}{{/depend}}
{{depend email_pec}}PEC: {{var email_pec}}{{/depend}}
{{depend cod_sdi}}SdI: {{var cod_sdi}}{{/depend}}
```

</details>

<details>
<summary><strong>Text One Line</strong> (click to expand)</summary>

```
{{depend prefix}}{{var prefix}} {{/depend}}{{var firstname}} {{depend middlename}}{{var middlename}} {{/depend}}{{var lastname}}{{depend suffix}} {{var suffix}}{{/depend}}, {{var street}}, {{var city}}, {{var region}} {{var postcode}}, {{var country}}{{depend cod_fisc}}, CF: {{var cod_fisc}}{{/depend}}{{depend email_pec}}, PEC: {{var email_pec}}{{/depend}}{{depend cod_sdi}}, SdI: {{var cod_sdi}}{{/depend}}
```

</details>

<details>
<summary><strong>HTML</strong> (click to expand)</summary>

```
{{depend prefix}}{{var prefix}} {{/depend}}{{var firstname}} {{depend middlename}}{{var middlename}} {{/depend}}{{var lastname}}{{depend suffix}} {{var suffix}}{{/depend}}<br/>
{{depend company}}{{var company}}<br />{{/depend}}
{{if street1}}{{var street1}}<br />{{/if}}
{{depend street2}}{{var street2}}<br />{{/depend}}
{{depend street3}}{{var street3}}<br />{{/depend}}
{{depend street4}}{{var street4}}<br />{{/depend}}
{{if city}}{{var city}},  {{/if}}{{if region}}{{var region}}, {{/if}}{{if postcode}}{{var postcode}}{{/if}}<br/>
{{var country}}<br/>
{{depend telephone}}T: {{var telephone}}{{/depend}}
{{depend vat_id}}<br/>VAT: {{var vat_id}}{{/depend}}{{depend cod_fisc}}<br/>CF: {{var cod_fisc}}{{/depend}}{{depend email_pec}}<br/>PEC: {{var email_pec}}{{/depend}}{{depend cod_sdi}}<br/>SdI: {{var cod_sdi}}{{/depend}}
```

</details>

<details>
<summary><strong>PDF</strong> (click to expand)</summary>

```
{{depend prefix}}{{var prefix}} {{/depend}}{{var firstname}} {{depend middlename}}{{var middlename}} {{/depend}}{{var lastname}}{{depend suffix}} {{var suffix}}{{/depend}}|
{{depend company}}{{var company}}|{{/depend}}
{{if street1}}{{var street1}}
{{/if}}
{{depend street2}}{{var street2}}|{{/depend}}
{{depend street3}}{{var street3}}|{{/depend}}
{{depend street4}}{{var street4}}|{{/depend}}
{{if city}}{{var city}},|{{/if}}
{{if region}}{{var region}}, {{/if}}{{if postcode}}{{var postcode}}{{/if}}|
{{var country}}|
{{depend telephone}}T: {{var telephone}}{{/depend}}|
{{depend vat_id}}<br/>VAT: {{var vat_id}}{{/depend}}|{{depend cod_fisc}}<br/>CF: {{var cod_fisc}}{{/depend}}|{{depend email_pec}}<br/>PEC: {{var email_pec}}{{/depend}}|{{depend cod_sdi}}<br/>SdI: {{var cod_sdi}}{{/depend}}|
```

</details>

<details>
<summary><strong>JavaScript Template</strong> (click to expand)</summary>

```
#{prefix} #{firstname} #{middlename} #{lastname} #{suffix}<br/>#{company}<br/>#{street0}<br/>#{street1}<br/>#{street2}<br/>#{street3}<br/>#{city}, #{region}, #{postcode}<br/>#{country_id}<br/>T: #{telephone}<br/>VAT: #{vat_id}<br/>CF: #{cod_fisc}<br/>PEC: #{email_pec}<br/>SdI: #{cod_sdi}
```

</details>

## Development

This module ships with the standard Maho CI gates:

- **PHPUnit** — `composer test` (bootstraps Maho from the module's own vendor)
- **PHPStan** (level 8) — `composer phpstan`
- **Rector** (dry-run) — `composer rector`
- **PHP CS Fixer** (dry-run) — `composer cs` (or `composer cs-fix` to apply)
- **PHP / XML syntax checks** — automatic on CI

Run `composer install` and you can execute any of the above locally before pushing, or run them all at once with `composer check`.