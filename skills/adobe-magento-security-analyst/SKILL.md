---
name: adobe-magento-security-analyst
description: "Security rules for Magento 2.4.8 code: admin and Web API ACL, ownership checks, CSRF and HTTP method, output escaping and CSP, SQL binding, mass assignment, encrypted secrets, file uploads, logs. Use when writing or reviewing controllers, webapi.xml, GraphQL resolvers, templates, queries, config fields or uploads. Not for server hardening or PCI infrastructure."
---

# Magento security

Rules for the code a change adds. Each surface (controller, route, resolver, template, query,
config field, upload) needs its protection named in the delivery report. Working examples:
`magento-example admin-controller`, `rest`, `graphql`, `config`, `viewmodel` (`adobe-magento-toolbox`).

## Rules

**Access**
- Every class under `Controller/Adminhtml` declares `public const ADMIN_RESOURCE =
  'Vendor_Module::action'`, one resource per capability (view, save, delete) in `etc/acl.xml`. The
  inherited default `Magento_Backend::admin` lets every admin user in.
- An admin listing or form exposing restricted data sets `<aclResource>` in its `dataSource`. Any
  admin can render a component directly through `mui/index/render`, skipping your controller.
- `webapi.xml`: a named resource by default. `self` for the logged-in customer's own data, with the
  id forced from the token: `<parameter name="customerId" force="true">%customer_id%</parameter>`.
  `anonymous` only when the business rule requires public access, then validate and cap everything.
- GraphQL: customer operations check `$context->getExtensionAttributes()->getIsCustomer()` and use
  `$context->getUserId()`, throwing `GraphQlAuthorizationException`; never take the customer id from
  the arguments.
- Customer-owned data read or written by id (order, quote, company data, document) checks the owner
  (`UserContextInterface::getUserId()`) in the service, block or data provider too: they can be
  rendered without your controller.

**Requests**
- State changes only in actions implementing `HttpPostActionInterface`: the framework checks the
  form key on POST and never on GET. `CsrfAwareActionInterface` only for an external caller that
  cannot send a form key (webhook), with its own signature check (scaffold: `adobe-create-controller`).
- A raw request array never goes to `setData()`/`addData()`: copy the allowed fields through setters
  or `DataObjectHelper::populateWithArray()` against the interface, and take id, owner and flags from
  the session or route. Otherwise the client sets `customer_id`, status or price.

**Output**
- Every dynamic value in a `.phtml` goes through `$escaper`: `escapeHtml` for text, `escapeHtmlAttr`
  for attributes, `escapeUrl` for `href`/`src`, `escapeJs` inside a JS string. `@noEscape` only for
  JSON from `Magento\Framework\Serialize\Serializer\JsonHexTag`. `$block->escape*()` is deprecated.
- JavaScript starts through `x-magento-init` or `data-mage-init`; if an inline tag is unavoidable, use
  `$secureRenderer->renderTag()`. Third-party script, style or frame hosts go in
  `etc/csp_whitelist.xml`: checkout runs CSP in restrict mode and blocks what is not listed.

**Data**
- Values reach SQL only as bind parameters (`where('sku = ?', $sku)`, the `$bind` argument of
  `fetchAll`/`query`) or through collection and SearchCriteria filters. Column, table and sort names
  from input go through an allowlist: `setOrder()` writes the field name into SQL as is.
- Secret config fields: `type="obscure"` with `Magento\Config\Model\Config\Backend\Encrypted`, read with
  `EncryptorInterface::decrypt()`, and the path listed in the `sensitive` argument of
  `Magento\Config\Model\Config\TypePool` (`di.xml`) so `app:config:dump` never writes it to
  `app/etc/config.php`.
- Uploads: `Magento\MediaStorage\Model\File\UploaderFactory` with `setAllowedExtensions()` (without it
  any extension passes), `setAllowRenameFiles(true)` and the result of `checkMimeType()` enforced.
  Public files in a module folder under `pub/media`; private files under `var/`, served by a
  controller that checks ACL or ownership. Never use the client file name as a path.
- Logs carry ids and statuses, never tokens, passwords, keys, card data, full payloads or personal
  data (`adobe-inject-custom-logger`). Log files outlive and outreach the database.

## Steps

1. List the entry points the change adds or alters.
2. Apply the matching rule to each one.
3. Run the checks below.
4. In the report, one line per entry point: the resource, owner check or escaping that protects it.

## Verify

```bash
grep -rL "ADMIN_RESOURCE" app/code/Vendor/Module/Controller/Adminhtml     # expect no output
grep -n 'ref="anonymous"' app/code/Vendor/Module/etc/webapi.xml          # each one justified
grep -rn '?= \$' app/code/Vendor/Module/view | grep -v 'escaper\|noEscape'   # unescaped output
bin/magento config:show section/group/secret_field                       # prints ****** when encrypted
curl -s -o /dev/null -w '%{http_code}\n' -X POST "$BASE_URL/rest/V1/route"   # no token: 401
curl -s -H "Authorization: Bearer $TOKEN_CUSTOMER_A" "$BASE_URL/rest/V1/route/ID_OF_B"   # no data of B
```

## Pitfalls

- ACL "done" because `acl.xml` has the resource → the controller constant and the route `<resource>`
  are what get checked.
- `self` route taking `customerId` from the URL → force it with `%customer_id%`.
- Ownership checked in the controller only → the data provider rendered through `mui/index/render`
  still serves any id.
- `escapeHtml` inside an attribute or URL → use the escaper for that context.
- Secret saved through a plain `text` field → existing values stay in clear text; re-save after
  switching to `obscure` with the `Encrypted` backend.

## Docs

- https://developer.adobe.com/commerce/php/development/security/
- https://developer.adobe.com/commerce/php/development/security/cross-site-scripting
- https://developer.adobe.com/commerce/php/development/security/content-security-policies
- https://developer.adobe.com/commerce/php/development/security/non-secure-functions
- https://developer.adobe.com/commerce/php/development/configuration/sensitive-environment-settings
- https://developer.adobe.com/commerce/webapi/get-started/authentication/
- https://developer.adobe.com/commerce/php/tutorials/backend/create-access-control-list-rule
