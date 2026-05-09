# Thai PromptPay

Generate **and read** Thai PromptPay QR codes in PHP.

- Generate payment-request QRs (EMVCo TLV) for **phone numbers**,
  **national / tax IDs**, **e-wallet IDs**, and **Bill Payment** with
  Ref1 / Ref2 reference codes for business transactions.
- Parse payment-confirmation slip QRs (the unified ITMX Slip Verify Mini-QR
  used by every Thai bank, plus the TrueMoney Wallet variant) — directly
  from a payload string or from a slip image.

Laravel-friendly: ships with a service provider, facade, and config —
auto-discovered.

## Installation

```bash
composer require awcode/thaipromptpay
```

QR rendering uses [`bacon/bacon-qr-code`](https://github.com/Bacon/BaconQrCode),
already pulled in by the dev requires. SVG works out of the box (needs only
`ext-dom`); PNG additionally requires `ext-imagick` or `ext-gd`.

## Quick start

```php
use Awcode\ThaiPromptpay\ThaiPromptpay;

// Static QR — anyone can scan, they enter the amount
$payload = ThaiPromptpay::phone('0899999999')->build();
echo $payload;
// 00020101021129370016A000000677010111011300668999999995802TH53037646304FE29

// Dynamic QR — locks in an amount
$payload = ThaiPromptpay::phone('0899999999')->amount(420)->build();

// Render
echo $payload->svg();          // SVG markup string
file_put_contents('qr.png', $payload->png(400));
echo '<img src="' . $payload->dataUri(300) . '">';
```

## Recipient types

```php
ThaiPromptpay::phone('0899999999');                // mobile (9–12 digits, dashes/spaces ok)
ThaiPromptpay::nationalId('1234567890123');        // Thai NID / Tax ID (13 digits)
ThaiPromptpay::eWallet('012345678901234');         // e-Wallet ID (15 digits)
ThaiPromptpay::billPayment('099400015804189');     // Bill Payment biller (13–15 digits)
```

The package auto-detects the type and sets the correct EMVCo merchant-info
sub-IDs (`01`/`02`/`03`) and AID:

- `A000000677010111` for personal credit transfer (tag 29)
- `A000000677010112` for domestic Bill Payment (tag 30)
- `A000000677012006` for cross-border Bill Payment

## Bill Payment with Ref1 / Ref2

For business transactions, attach reference codes — Ref1 (required, e.g.
invoice number) and Ref2 (optional, e.g. customer code):

```php
$payload = ThaiPromptpay::billPayment('099400015804189')
    ->ref1('INV001')
    ->ref2('CUST123')
    ->amount(1500)
    ->build();
```

References are validated and uppercased: alphanumeric only, max 20 chars
each (per Bank of Thailand spec). Mixed case input is accepted and
normalized.

For cross-border bill payment QRs:

```php
ThaiPromptpay::billPayment('099400015804189')
    ->ref1('INV001')
    ->crossBorder()
    ->build();
```

## Laravel

The service provider and facade alias are auto-discovered. After install:

```php
use ThaiPromptpay;            // facade alias

Route::get('/qr/{invoice}', function (Invoice $invoice) {
    $payload = ThaiPromptpay::billPayment(config('thaipromptpay.biller_id'))
        ->ref1($invoice->number)
        ->ref2($invoice->customer_code)
        ->amount($invoice->total)
        ->build();

    return response($payload->png(400), 200, ['Content-Type' => 'image/png']);
});
```

Or inject it:

```php
use Awcode\ThaiPromptpay\ThaiPromptpay;

public function show(ThaiPromptpay $promptpay, Invoice $invoice)
{
    $payload = $promptpay::billPayment(config('thaipromptpay.biller_id'))
        ->ref1($invoice->number)
        ->amount($invoice->total)
        ->build();

    return view('invoice.qr', ['qr' => $payload->dataUri()]);
}
```

Publish the config to override defaults:

```bash
php artisan vendor:publish --tag=thaipromptpay-config
```

```env
PROMPTPAY_PHONE=0899999999
PROMPTPAY_NATIONAL_ID=1234567890123
PROMPTPAY_BILLER_ID=099400015804189
```

## API reference

### `ThaiPromptpay`

| Method | Returns | Description |
|---|---|---|
| `::phone(string $phone)` | `Builder` | Mobile phone target |
| `::nationalId(string $id)` | `Builder` | Thai NID / Tax ID |
| `::eWallet(string $id)` | `Builder` | 15-digit e-wallet ID |
| `::billPayment(string $billerId)` | `Builder` | Biller ID for Bill Payment |
| `::parseSlip(string $payload)` | `SlipQr` | Parse a slip-verify Mini-QR payload string |
| `::scanSlip(string $image)` | `SlipQr` | Decode + parse from an image (path / bytes / data URI) |
| `::readSlip(string $input)` | `SlipQr` | Auto-detect payload string vs image |

### `Builder`

All mutators are immutable — they return a new builder.

| Method | Description |
|---|---|
| `->amount(float\|int\|string $amount)` | Make it dynamic. 2-decimal formatting |
| `->ref1(string $ref1)` | Bill Payment only. Required for Bill Payment |
| `->ref2(string $ref2)` | Bill Payment only. Optional |
| `->crossBorder(bool = true)` | Bill Payment only. Switch to cross-border AID |
| `->build()` | Returns a `Payload` |

### `Payload`

| Method | Returns |
|---|---|
| `->toString()` / `(string)` | EMVCo TLV payload |
| `->svg(int $size = 300, int $margin = 1)` | SVG markup |
| `->png(int $size = 300, int $margin = 1)` | Raw PNG bytes |
| `->dataUri(int $size = 300, int $margin = 1, string $format = 'svg')` | `data:` URI |

## Reading slip QRs

After a Thai bank transfer, the payer's app prints/displays a slip with a
QR. The package decodes that QR's structured contents, locally and offline.

```php
use Awcode\ThaiPromptpay\ThaiPromptpay;

// From a payload string already extracted from the QR
$slip = ThaiPromptpay::parseSlip('00460006...5102TH9104XXXX');

// From a slip image (file path, bytes, or data URI)
$slip = ThaiPromptpay::scanSlip('/path/to/slip.jpg');
$slip = ThaiPromptpay::scanSlip(file_get_contents('slip.png'));
$slip = ThaiPromptpay::scanSlip('data:image/png;base64,iVBORw0KG...');

// Auto-detect: payload string or image
$slip = ThaiPromptpay::readSlip($input);

$slip->apiId;           // "000001"  (or "01" for TrueMoney variant)
$slip->sendingBank;     // "014"     (3-digit ITMX SMART code)
$slip->bankShortName;   // "SCB"
$slip->bankNameEnglish; // "Siam Commercial Bank"
$slip->bankNameThai;    // "ธนาคารไทยพาณิชย์"
$slip->transRef;        // bank-issued transaction reference
$slip->isTrueMoney();   // bool
$slip->toArray();       // structured array
```

Image scanning requires `khanamiryan/qrcode-detector-decoder` (and `ext-gd`
or `ext-imagick`). Install if you need it:

```bash
composer require khanamiryan/qrcode-detector-decoder
```

### What slip parsing does *not* do

Decoding the QR tells you what the slip **claims**, not whether the
transaction actually happened. The QR itself is unsigned — anyone can craft
one with arbitrary fields and a valid CRC.

True verification means asking the issuing bank "is transaction `transRef`
real?" — and the Thai banks do not expose any anonymous public endpoint for
that. Verification requires either:

1. Bring your own bank Open API credentials (SCB Partners, K API, Bangkok
   Bank Developer) — typically requires merchant onboarding, mTLS, and
   per-bank monthly minimums.
2. A third-party aggregator (SlipOK, EasySlip, RDCW, Thunder, etc.) that
   licenses those bank APIs server-side.

**Roadmap (v2):**

- Aggregator-backed verification: SlipOK, EasySlip, RDCW.
- Bank Open API adapters for shops with their own credentials (SCB Partners,
  K API, Bangkok Bank Developer).
- BYO-OCR cross-check helpers: compare a QR's `transRef` against text you've
  extracted from the slip image — catches paste-job fakes where the QR and
  visible transRef don't agree (does **not** catch amount-only tampering;
  the QR has no amount to compare).
- Optional Tesseract integration for those who want the OCR built in.

v1 stays parse-only and has zero outbound network calls.

### Bank codes

The package resolves all major Thai bank codes (BBL, KBANK, KTB, SCB, BAY,
TTB, GSB, BAAC, TISCO, UOB, CIMB, KKP, LHB, ICBC, …). Look up directly via
`Awcode\ThaiPromptpay\Slip\BankCodes::lookup('014')`.

## How it works

The package implements the EMVCo Merchant-Presented QR Code spec as adopted
by the Bank of Thailand for PromptPay:

- Top-level fields in canonical order: `00 01 29|30 58 53 54 63`
- `29` (credit transfer) holds AID + phone/NID/e-wallet sub-fields
- `30` (Bill Payment) holds AID + biller + Ref1 + optional Ref2
- `63` is a CRC-16/CCITT-FALSE checksum (poly `0x1021`, init `0xFFFF`)
  computed over the entire payload including the literal bytes `6304`

Test vectors verified byte-for-byte against
[dtinth/promptpay-qr](https://github.com/dtinth/promptpay-qr) and
[kittinan/php-promptpay-qr](https://github.com/kittinan/php-promptpay-qr).

## Credits

Built on the work of:

- [dtinth/promptpay-qr](https://github.com/dtinth/promptpay-qr) (the original Node reference)
- [farzai/promptpay-qr-php](https://github.com/farzai/promptpay-qr-php)
- [kittinan/php-promptpay-qr](https://github.com/kittinan/php-promptpay-qr)
- [keenthekeen/php-promptpay-qr](https://github.com/keenthekeen/php-promptpay-qr) (Bill Payment / Ref1+Ref2 reference)

## License

MIT.
