---
description: "Put the rules of a flow in one service class that the admin, the storefront, the API and the CLI call, and extend the flow with one rewrite."
---

# Services

A service holds the rules of one flow. The admin, the storefront, the REST and GraphQL API and the
CLI all call the same service, so they apply the same rules.

**A transport translates, a service decides.** A transport is a controller, an API class or a CLI
command. It reads its input, calls one service method, and shows the result.

## Call a service

```php
/** @var Mage_Newsletter_Service_Subscriber $service */
$service = Mage::getService('newsletter/subscriber');
$subscriber = $service->subscribe($email, $storeId, $customerId, $fields);
```

`Mage::getService()` returns one instance for each alias, as `Mage::helper()` does. Never create a
service with `new`.

## Declare a service

A service is a class in the `Service` folder of its module. Declare the class group in the
`config.xml` of the module:

```xml
<global>
    <services>
        <mymodule>
            <class>My_Module_Service</class>
        </mymodule>
    </services>
</global>
```

`Mage::getService('mymodule/ticket')` then returns `My_Module_Service_Ticket`.

A service has no interface, no parent class, no constructor and no property. Name its methods
`getById()`, `save()` and `delete()`, plus one verb for each other action. `getById()` throws when
the record does not exist.

## Change a flow in an extension

Rewrite the service. The change then applies to each transport:

```xml
<global>
    <services>
        <newsletter>
            <rewrite>
                <subscriber>My_Module_Service_Subscriber</subscriber>
            </rewrite>
        </newsletter>
    </services>
</global>
```

## Where a rule goes

| Rule | Layer |
|------|-------|
| An invariant of one record, such as a required name or a value in range | The model or the resource model. An importer that saves the model gets it. |
| A gate: scope, ownership, a feature switch, a rate limit, a honeypot, a CAPTCHA | The service |
| A rule over several models | The service |
| A side effect: an email, an event, a reindex | The service |
| The switch that turns one endpoint on or off | The transport of that endpoint |
| The store restriction of an API token | The API edge. A service that needs the allowed stores takes them as data. |
| A list of values that an extension can add to | The configuration. Do not put it in `_beforeSave()`. |

A service takes and returns models and scalar values. It never reads the request or a session, and
it does not know which transport calls it. When a rule depends on the role of the caller, the
service takes `Mage_Checkout_Model_Actor` (`Customer` or `Staff`).

A gate takes the fields that the client sent, and it runs for each transport:

```php
Mage::helper('core')->assertCaptcha($fields);
```

The storefront controller gives its POST, and the API gives its body.

## Exceptions

A service never throws an HTTP exception. It throws the exception that names the fault, and each
transport shows it: the API as a status code, a controller as a session message, a command as an
exit code.

| Fault | Exception | API status |
|-------|-----------|------------|
| The client did not prove who it is | `Mage_Core_Exception_Unauthorized` | 401 |
| The store does not allow this client to do the action | `Mage_Core_Exception_Forbidden` | 403 |
| The record does not exist | `Mage_Core_Exception_NoSuchEntity` | 404 |
| The state of the record does not allow the action | `Mage_Core_Exception_Conflict` | 409 |
| Errors of named fields | `Mage_Core_Exception_Input` | 422 with `details.errors` |
| Other content that the service cannot accept | `Mage_Core_Exception` | 422 |
| A rate limit refuses the client | `Mage_Core_Exception_TooManyRequests` | 429 with `Retry-After` |
| A fault of the server | `\RuntimeException` | 500 |

REST and GraphQL give the same status, the same `details` and the same retry delay.

## Shared code

Use these methods. Do not copy their logic into a service.

- `$model->load($id)` finds no row for an ID that the column cannot hold, on each database. A
  service needs no guard around a load.
- `Mage_Core_Exception_Input::assertValid($model->validate(), 'field')` turns the result of a
  `validate()` into field errors and throws them. `addValidationResult()` adds them to an exception
  that collects more errors.
- `$limiter->attemptOrThrow()` checks a rate limit, records the attempt and throws the 429.
  `throwIfTooManyAttempts()` only checks, for a limit that counts failures.

See [Rate limiting & honeypot](rate-limiting-honeypot.md) for the limiters.

## Tests that guard the standard

- `tests/Backend/Unit/Maho/ServiceLayoutTest.php` checks the folder, the alias and the shape of
  each service class.
- `tests/Backend/Unit/Maho/LayeringConventionTest.php` fails when a transport decides a status,
  saves or deletes a model, or checks a rate limit, and when a service reads the request, the
  session, the area or the API module. A flow that only one transport starts can keep its write in
  that transport, with an entry in the permanent list of the test.
- Each flow has a parity test. It drives each transport and compares the stored result and the
  refusals.
