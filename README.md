# omnibus/zto-express

ZTO Express (中通快递) for [glitchr/omnibus](https://github.com/glitchr-studio/omnibus): orders
with electronic waybills, tracking and cancellation - the open platform's signed JSON APIs.
Prices come from configuration (`rates`): ZTO quotes by contract.

```yaml
omnibus:
    gateways:
        zto-express:
            factory: zto-express
            options:
                company_id: '%env(ZTO_COMPANY_ID)%'
                key: '%env(ZTO_KEY)%'
                partner_code: '%env(ZTO_PARTNER)%'   # 合作商编码, for electronic waybills
                sandbox: true
                rates: [...]
```

Addresses: the first street line is the street, the second the district (区), the third the
province (省). The label is ZTO's print data (JSON: the sorting marks), rendered by your own
template. Shipment options: `type` (1 by default), `description`, `instructions`. No pickup
points.

Credentials: a company on [ZTO's open platform](https://open.zto.com) gives the company id and
key (the test environment first) and the partner code for electronic waybills.

Built from ZTO's published open platform documentation and tested on recorded answers;
**unverified** against the test environment until an account's keys are at hand.

License: LGPL-3.0-or-later.
