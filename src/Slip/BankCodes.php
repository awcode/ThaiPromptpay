<?php

declare(strict_types=1);

namespace Awcode\ThaiPromptpay\Slip;

/**
 * ITMX / Thai SMART 3-digit bank codes used in slip-verify Mini-QRs.
 *
 * Source: ITMX SMART code list, cross-referenced with the published bank
 * developer portals. These codes appear as Tag 00 / Sub-tag 01 in every
 * Thai slip-verify Mini-QR.
 */
final class BankCodes
{
    /** @var array<string, array{short: string, name_en: string, name_th: string}> */
    private const CODES = [
        '002' => ['short' => 'BBL',    'name_en' => 'Bangkok Bank',                       'name_th' => 'ธนาคารกรุงเทพ'],
        '004' => ['short' => 'KBANK',  'name_en' => 'Kasikornbank',                        'name_th' => 'ธนาคารกสิกรไทย'],
        '006' => ['short' => 'KTB',    'name_en' => 'Krung Thai Bank',                     'name_th' => 'ธนาคารกรุงไทย'],
        '011' => ['short' => 'TTB',    'name_en' => 'TMBThanachart Bank',                  'name_th' => 'ธนาคารทหารไทยธนชาต'],
        '014' => ['short' => 'SCB',    'name_en' => 'Siam Commercial Bank',                'name_th' => 'ธนาคารไทยพาณิชย์'],
        '017' => ['short' => 'CITI',   'name_en' => 'Citibank',                            'name_th' => 'ธนาคารซิตี้แบงก์'],
        '018' => ['short' => 'SMBC',   'name_en' => 'Sumitomo Mitsui Banking Corporation', 'name_th' => 'ธนาคารซูมิโตโม มิตซุย แบงกิ้ง คอร์ปอเรชั่น'],
        '020' => ['short' => 'SCBT',   'name_en' => 'Standard Chartered (Thai)',           'name_th' => 'ธนาคารสแตนดาร์ดชาร์เตอร์ด (ไทย)'],
        '022' => ['short' => 'CIMBT',  'name_en' => 'CIMB Thai Bank',                      'name_th' => 'ธนาคารซีไอเอ็มบี ไทย'],
        '024' => ['short' => 'UOBT',   'name_en' => 'United Overseas Bank (Thai)',         'name_th' => 'ธนาคารยูโอบี'],
        '025' => ['short' => 'BAY',    'name_en' => 'Bank of Ayudhya (Krungsri)',          'name_th' => 'ธนาคารกรุงศรีอยุธยา'],
        '030' => ['short' => 'GSB',    'name_en' => 'Government Savings Bank',             'name_th' => 'ธนาคารออมสิน'],
        '031' => ['short' => 'HSBC',   'name_en' => 'HSBC Thailand',                       'name_th' => 'ธนาคารฮ่องกงและเซี่ยงไฮ้'],
        '033' => ['short' => 'GHB',    'name_en' => 'Government Housing Bank',             'name_th' => 'ธนาคารอาคารสงเคราะห์'],
        '034' => ['short' => 'BAAC',   'name_en' => 'Bank for Agriculture and Agricultural Cooperatives', 'name_th' => 'ธนาคารเพื่อการเกษตรและสหกรณ์การเกษตร'],
        '039' => ['short' => 'MIZUHO', 'name_en' => 'Mizuho Bank',                         'name_th' => 'ธนาคารมิซูโฮ'],
        '066' => ['short' => 'IBANK',  'name_en' => 'Islamic Bank of Thailand',            'name_th' => 'ธนาคารอิสลามแห่งประเทศไทย'],
        '067' => ['short' => 'TISCO',  'name_en' => 'TISCO Bank',                          'name_th' => 'ธนาคารทิสโก้'],
        '069' => ['short' => 'KKP',    'name_en' => 'Kiatnakin Phatra Bank',               'name_th' => 'ธนาคารเกียรตินาคินภัทร'],
        '070' => ['short' => 'ICBCT',  'name_en' => 'ICBC (Thai)',                         'name_th' => 'ธนาคารไอซีบีซี (ไทย)'],
        '071' => ['short' => 'TCRB',   'name_en' => 'Thai Credit Bank',                    'name_th' => 'ธนาคารไทยเครดิต'],
        '073' => ['short' => 'LHB',    'name_en' => 'Land and Houses Bank',                'name_th' => 'ธนาคารแลนด์ แอนด์ เฮ้าส์'],
    ];

    public static function shortName(string $code): ?string
    {
        return self::CODES[$code]['short'] ?? null;
    }

    public static function englishName(string $code): ?string
    {
        return self::CODES[$code]['name_en'] ?? null;
    }

    public static function thaiName(string $code): ?string
    {
        return self::CODES[$code]['name_th'] ?? null;
    }

    /** @return array{short: string, name_en: string, name_th: string}|null */
    public static function lookup(string $code): ?array
    {
        return self::CODES[$code] ?? null;
    }

    /** @return array<string, array{short: string, name_en: string, name_th: string}> */
    public static function all(): array
    {
        return self::CODES;
    }
}
