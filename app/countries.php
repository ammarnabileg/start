<?php
/**
 * دولة الشركة: كل الأمثلة (العملة، الأرقام، المدن، طرق الدفع، الإجازة، اللهجة) والذكاء الاصطناعي بيتبنوا عليها.
 * الأمثلة هنا بيانات (مش ترجمة)، فبتتكتب بالعربي والإنجليزي مباشرة.
 */
function countries(): array
{
    static $c = null; if ($c) return $c;
    // code => [ar, en, flag, region, tz, cur, cur_ar, cur_en, phone, city_ar, city_en, area_ar, area_en, pay_ar, pay_en, weekend, dialect_ar, dialect_en]
    $rows = [
        'SA' => ['السعودية', 'Saudi Arabia', '🇸🇦', 'gulf', 'Asia/Riyadh', 'SAR', 'ريال', 'SAR', '+966501234567', 'الرياض', 'Riyadh', 'حي العليا', 'Al Olaya', 'مدى، Apple Pay، STC Pay، تابي، تمارا', 'mada, Apple Pay, STC Pay, Tabby, Tamara', ['fri', 'sat'], 'السعودية', 'Saudi'],
        'AE' => ['الإمارات', 'UAE', '🇦🇪', 'gulf', 'Asia/Dubai', 'AED', 'درهم', 'AED', '+971501234567', 'دبي', 'Dubai', 'الخليج التجاري', 'Business Bay', 'بطاقات، Apple Pay، تابي، الدفع عند الاستلام', 'Cards, Apple Pay, Tabby, cash on delivery', ['sat', 'sun'], 'الإماراتية', 'Emirati'],
        'KW' => ['الكويت', 'Kuwait', '🇰🇼', 'gulf', 'Asia/Kuwait', 'KWD', 'دينار', 'KWD', '+96550012345', 'الكويت', 'Kuwait City', 'السالمية', 'Salmiya', 'كي نت، Apple Pay، بطاقات', 'KNET, Apple Pay, cards', ['fri', 'sat'], 'الكويتية', 'Kuwaiti'],
        'QA' => ['قطر', 'Qatar', '🇶🇦', 'gulf', 'Asia/Qatar', 'QAR', 'ريال قطري', 'QAR', '+97433123456', 'الدوحة', 'Doha', 'الخليج الغربي', 'West Bay', 'بطاقات، Apple Pay، الدفع عند الاستلام', 'Cards, Apple Pay, cash on delivery', ['fri', 'sat'], 'القطرية', 'Qatari'],
        'BH' => ['البحرين', 'Bahrain', '🇧🇭', 'gulf', 'Asia/Bahrain', 'BHD', 'دينار بحريني', 'BHD', '+97333123456', 'المنامة', 'Manama', 'السيف', 'Seef', 'بنفت باي، بطاقات، Apple Pay', 'BenefitPay, cards, Apple Pay', ['fri', 'sat'], 'البحرينية', 'Bahraini'],
        'OM' => ['عُمان', 'Oman', '🇴🇲', 'gulf', 'Asia/Muscat', 'OMR', 'ريال عُماني', 'OMR', '+96891234567', 'مسقط', 'Muscat', 'القرم', 'Al Qurum', 'بطاقات، تحويل بنكي، الدفع عند الاستلام', 'Cards, bank transfer, cash on delivery', ['fri', 'sat'], 'العُمانية', 'Omani'],
        'EG' => ['مصر', 'Egypt', '🇪🇬', 'nile', 'Africa/Cairo', 'EGP', 'جنيه', 'EGP', '+201001234567', 'القاهرة', 'Cairo', 'مدينة نصر', 'Nasr City', 'كاش، فيزا، فودافون كاش، إنستاباي', 'Cash, card, Vodafone Cash, InstaPay', ['fri'], 'المصرية', 'Egyptian'],
        'SD' => ['السودان', 'Sudan', '🇸🇩', 'nile', 'Africa/Khartoum', 'SDG', 'جنيه سوداني', 'SDG', '+249912345678', 'الخرطوم', 'Khartoum', 'الرياض', 'Al Riyadh', 'بنكك، كاش', 'Bankak, cash', ['fri', 'sat'], 'السودانية', 'Sudanese'],
        'JO' => ['الأردن', 'Jordan', '🇯🇴', 'levant', 'Asia/Amman', 'JOD', 'دينار', 'JOD', '+962791234567', 'عمّان', 'Amman', 'عبدون', 'Abdoun', 'كليك (CliQ)، بطاقات، كاش', 'CliQ, cards, cash', ['fri'], 'الأردنية', 'Jordanian'],
        'LB' => ['لبنان', 'Lebanon', '🇱🇧', 'levant', 'Asia/Beirut', 'USD', 'دولار', 'USD', '+96171123456', 'بيروت', 'Beirut', 'الحمرا', 'Hamra', 'كاش، OMT، Whish', 'Cash, OMT, Whish', ['sun'], 'اللبنانية', 'Lebanese'],
        'SY' => ['سوريا', 'Syria', '🇸🇾', 'levant', 'Asia/Damascus', 'SYP', 'ليرة', 'SYP', '+963931234567', 'دمشق', 'Damascus', 'المزة', 'Mezzeh', 'كاش، حوالة', 'Cash, money transfer', ['fri'], 'السورية', 'Syrian'],
        'PS' => ['فلسطين', 'Palestine', '🇵🇸', 'levant', 'Asia/Hebron', 'ILS', 'شيكل', 'ILS', '+970591234567', 'رام الله', 'Ramallah', 'الطيرة', 'Al Tireh', 'كاش، تحويل بنكي، بطاقات', 'Cash, bank transfer, cards', ['fri'], 'الفلسطينية', 'Palestinian'],
        'IQ' => ['العراق', 'Iraq', '🇮🇶', 'levant', 'Asia/Baghdad', 'IQD', 'دينار عراقي', 'IQD', '+9647701234567', 'بغداد', 'Baghdad', 'الكرادة', 'Karrada', 'زين كاش، FastPay، كاش', 'ZainCash, FastPay, cash', ['fri', 'sat'], 'العراقية', 'Iraqi'],
        'YE' => ['اليمن', 'Yemen', '🇾🇪', 'gulf', 'Asia/Aden', 'YER', 'ريال يمني', 'YER', '+967771234567', 'صنعاء', "Sana'a", 'حدة', 'Hadda', 'كاش، حوالة', 'Cash, money transfer', ['fri'], 'اليمنية', 'Yemeni'],
        'MA' => ['المغرب', 'Morocco', '🇲🇦', 'maghreb', 'Africa/Casablanca', 'MAD', 'درهم', 'MAD', '+212612345678', 'الدار البيضاء', 'Casablanca', 'المعاريف', 'Maarif', 'الدفع عند الاستلام، بطاقات، تحويل بنكي', 'Cash on delivery, cards, bank transfer', ['sun'], 'المغربية (الدارجة)', 'Moroccan Darija'],
        'DZ' => ['الجزائر', 'Algeria', '🇩🇿', 'maghreb', 'Africa/Algiers', 'DZD', 'دينار جزائري', 'DZD', '+213551234567', 'الجزائر العاصمة', 'Algiers', 'حيدرة', 'Hydra', 'الدفع عند الاستلام، بطاقة CIB، بريدي موب', 'Cash on delivery, CIB card, BaridiMob', ['fri', 'sat'], 'الجزائرية', 'Algerian'],
        'TN' => ['تونس', 'Tunisia', '🇹🇳', 'maghreb', 'Africa/Tunis', 'TND', 'دينار تونسي', 'TND', '+21620123456', 'تونس العاصمة', 'Tunis', 'المنزه', 'El Menzah', 'كاش، بطاقات، D17', 'Cash, cards, D17', ['sun'], 'التونسية', 'Tunisian'],
        'LY' => ['ليبيا', 'Libya', '🇱🇾', 'maghreb', 'Africa/Tripoli', 'LYD', 'دينار ليبي', 'LYD', '+218911234567', 'طرابلس', 'Tripoli', 'قرجي', 'Gargaresh', 'كاش، بطاقات مصرفية', 'Cash, bank cards', ['fri', 'sat'], 'الليبية', 'Libyan'],
        'TR' => ['تركيا', 'Türkiye', '🇹🇷', 'other', 'Europe/Istanbul', 'TRY', 'ليرة تركية', 'TRY', '+905321234567', 'إسطنبول', 'Istanbul', 'كاديكوي', 'Kadıköy', 'بطاقات، تحويل بنكي', 'Cards, bank transfer', ['sun'], '', ''],
        'GB' => ['المملكة المتحدة', 'United Kingdom', '🇬🇧', 'other', 'Europe/London', 'GBP', 'جنيه إسترليني', 'GBP', '+447700900123', 'لندن', 'London', 'كامدن', 'Camden', 'بطاقات، Apple Pay، PayPal', 'Cards, Apple Pay, PayPal', ['sat', 'sun'], '', ''],
        'US' => ['الولايات المتحدة', 'United States', '🇺🇸', 'other', 'America/New_York', 'USD', 'دولار', 'USD', '+12025550123', 'نيويورك', 'New York', 'مانهاتن', 'Manhattan', 'بطاقات، Apple Pay، PayPal', 'Cards, Apple Pay, PayPal', ['sat', 'sun'], '', ''],
        'DE' => ['ألمانيا', 'Germany', '🇩🇪', 'other', 'Europe/Berlin', 'EUR', 'يورو', 'EUR', '+4915112345678', 'برلين', 'Berlin', 'ميته', 'Mitte', 'بطاقات، PayPal، Klarna', 'Cards, PayPal, Klarna', ['sat', 'sun'], '', ''],
        'FR' => ['فرنسا', 'France', '🇫🇷', 'other', 'Europe/Paris', 'EUR', 'يورو', 'EUR', '+33612345678', 'باريس', 'Paris', 'لو ماريه', 'Le Marais', 'بطاقات، PayPal', 'Cards, PayPal', ['sat', 'sun'], '', ''],
    ];
    $keys = ['ar', 'en', 'flag', 'region', 'tz', 'cur', 'cur_ar', 'cur_en', 'phone', 'city_ar', 'city_en', 'area_ar', 'area_en', 'pay_ar', 'pay_en', 'weekend', 'dialect_ar', 'dialect_en'];
    // باقي دول العالم (بيانات أساسية: الاسم، العملة، التوقيت، كود الاتصال). الدول اللي فوق ليها بيانات أدق
    $world = is_file(__DIR__ . '/countries_world.php') ? (array)require __DIR__ . '/countries_world.php' : [];
    $arabOther = ['MR', 'SO', 'DJ', 'KM'];
    $zoneRegion = function (string $tz): string {
        return ['Europe' => 'europe', 'Asia' => 'asia', 'Africa' => 'africa', 'America' => 'americas', 'Australia' => 'oceania', 'Pacific' => 'oceania', 'Indian' => 'asia', 'Atlantic' => 'europe'][strtok($tz, '/')] ?? 'other';
    };
    $c = [];
    foreach ($rows as $code => $r) {
        $x = array_combine($keys, $r) + ['code' => $code];
        if ($x['region'] === 'other') $x['region'] = $zoneRegion($x['tz']);
        $c[$code] = $x;
    }
    foreach ($world as $code => $r) {
        if (isset($c[$code]) || count($r) !== count($keys)) continue;
        $x = array_combine($keys, $r) + ['code' => $code];
        if (in_array($code, $arabOther, true)) $x['region'] = 'arab';
        $c[$code] = $x;
    }
    $dial = is_file(__DIR__ . '/country_dial.php') ? (array)require __DIR__ . '/country_dial.php' : [];
    foreach ($c as $code => &$x) $x['dial'] = isset($dial[$code]) ? '+' . $dial[$code] : preg_replace('/^(\+\d{1,3}).*/', '$1', (string)$x['phone']);
    unset($x);
    return $c;
}

function country_regions(): array
{
    return ['gulf' => t('الخليج والجزيرة العربية'), 'nile' => t('وادي النيل'), 'levant' => t('الشام والعراق'), 'maghreb' => t('المغرب العربي'), 'arab' => t('دول عربية أخرى'),
        'europe' => t('أوروبا'), 'asia' => t('آسيا'), 'africa' => t('أفريقيا'), 'americas' => t('الأمريكتين'), 'oceania' => t('أوقيانوسيا'), 'other' => t('دول أخرى')];
}

/** دولة الشركة الحالية. لو مش متحددة: من المنطقة الزمنية */
function ws_country(?int $wid = null): string
{
    $wid = $wid ?? (int)ws('id');
    $code = strtoupper((string)setting('country_' . $wid, ''));
    if (isset(countries()[$code])) return $code;
    $tz = $wid === (int)ws('id') ? (string)ws('timezone') : '';
    foreach (countries() as $k => $c) if ($c['tz'] === $tz) return $k;
    return 'EG';
}
function ws_country_set(): bool { return isset(countries()[strtoupper((string)setting('country_' . (int)ws('id'), ''))]); }

/** قيمة من بيانات الدولة باللغة الحالية: cx('city') → الرياض أو Riyadh */
function cx(string $key, ?string $code = null)
{
    $c = countries()[$code ?? ws_country()] ?? countries()['EG'];
    if (isset($c[$key . '_ar'])) return lang() === 'ar' ? $c[$key . '_ar'] : $c[$key . '_en'];
    return $c[$key] ?? '';
}
function cx_name(?string $code = null): string { return (string)cx(lang() === 'ar' ? 'ar' : 'en', $code); }
/** رقم مثال بالصيغة الدولية، بدون + لو $plus = false */
function cx_phone(bool $plus = true): string { $p = (string)cx('phone'); return $plus ? $p : ltrim($p, '+'); }
function cx_weekend_days(): array { $n = ['sat' => t('السبت'), 'sun' => t('الأحد'), 'mon' => t('الاثنين'), 'tue' => t('الثلاثاء'), 'wed' => t('الأربعاء'), 'thu' => t('الخميس'), 'fri' => t('الجمعة')]; $o = []; foreach ((array)cx('weekend') as $d) $o[$d] = $n[$d]; return $o; }

/** سطر للذكاء الاصطناعي: الأمثلة والعملة واللهجة كلها لازم تناسب الدولة دي بس */
function ai_country_note(): string
{
    $c = countries()[ws_country()];
    return 'BUSINESS COUNTRY: ' . $c['en'] . ' (' . $c['ar'] . '). Currency: ' . $c['cur'] . ' (' . $c['cur_ar'] . '). Phone format: ' . preg_replace('/^(\+\d{1,3}).*/', '$1', $c['phone']) . '…'
        . '. Weekend: ' . implode('/', $c['weekend']) . '. Main city example: ' . $c['city_en'] . '.'
        . ($c['dialect_en'] !== '' ? ' Local Arabic dialect: ' . $c['dialect_en'] . '.' : '')
        . ' Every example, city, district, currency, payment method, holiday, cultural reference and slang you produce must fit ' . $c['en'] . ' specifically — never use examples from another country (e.g. do not mention another Gulf country\'s cities or currency) unless the business data itself does.';
}
