<?php
/**
 * منشئ الحملات المتقدم: حملة ← مجموعات إعلانية ← إعلانات، لكل المنصات اللي زيرنيو بيدعمها.
 * المشروع بيتحفظ JSON في ad_builds، والإطلاق بيتم خطوة بخطوة (ab_launch_plan / ab_launch_step).
 */

/** المنصة الموحدة من منصة الحساب المربوط */
function ab_platform_of(string $connPlatform): string
{
    $m = ['facebook' => 'meta', 'instagram' => 'meta', 'metaads' => 'meta', 'linkedin' => 'linkedin', 'linkedinads' => 'linkedin',
        'tiktok' => 'tiktok', 'tiktokads' => 'tiktok', 'twitter' => 'x', 'xads' => 'x', 'pinterest' => 'pinterest', 'pinterestads' => 'pinterest',
        'googleads' => 'google', 'redditads' => 'reddit', 'microsoftads' => 'microsoft'];
    return $m[$connPlatform] ?? $connPlatform;
}

function ab_platform_label(string $p): string
{
    return ['meta' => 'Meta (Facebook / Instagram)', 'google' => 'Google Ads', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest',
        'x' => 'X', 'reddit' => 'Reddit', 'microsoft' => 'Microsoft Ads', 'openai' => 'ChatGPT Ads'][$p] ?? $p;
}

/** بيانات مشروع جديد */
function ab_default_data(string $platform, string $connId, string $adAccountId, string $currency, string $accName): array
{
    $country = function_exists('ws_country') ? ws_country() : 'EG';
    return [
        'v' => 1, 'platform' => $platform, 'connId' => $connId, 'adAccountId' => $adAccountId, 'currency' => $currency, 'accName' => $accName,
        'campaign' => ['name' => '', 'goal' => 'traffic', 'budgetLevel' => 'adset', 'budget' => ['amount' => '', 'type' => 'daily'], 'bidStrategy' => 'LOWEST_COST_WITHOUT_CAP',
            'bidAmount' => '', 'roasFloor' => '', 'startDate' => '', 'endDate' => '', 'specialAdCategories' => [], 'status' => 'PAUSED'],
        'adSets' => [ab_default_adset($country, 1)],
    ];
}

function ab_default_adset(string $country, int $n): array
{
    return [
        'id' => 's' . bin2hex(random_bytes(3)), 'name' => '', 'budget' => ['amount' => '', 'type' => 'daily'], 'optimizationGoal' => '',
        'targeting' => ['countries' => [$country], 'excludeCountries' => [], 'regions' => [], 'cities' => [], 'ageMin' => 18, 'ageMax' => 65, 'gender' => 'all',
            'languages' => [], 'interests' => [], 'behaviors' => [], 'workPositions' => [], 'workIndustries' => [], 'audienceId' => '', 'audienceExclude' => [],
            'placementsMode' => 'auto', 'placements' => [], 'userDevice' => [], 'userOs' => []],
        'ads' => [ab_default_ad()],
    ];
}

function ab_default_ad(): array
{
    return ['id' => 'a' . bin2hex(random_bytes(3)), 'name' => '', 'format' => 'image', 'headline' => '', 'body' => '', 'headlines' => [], 'bodies' => [],
        'cta' => 'LEARN_MORE', 'linkUrl' => '', 'imageUrl' => '', 'videoUrl' => '', 'thumbnailUrl' => '', 'cards' => [], 'leadGenFormId' => '', 'whatsappPhoneNumber' => ''];
}

function ab_list(int $wid): array
{
    $st = db()->prepare("SELECT id, name, platform, state, updated_at, created_by FROM ad_builds WHERE workspace_id = ? ORDER BY updated_at DESC LIMIT 100");
    $st->execute([$wid]);
    return $st->fetchAll();
}

function ab_get(int $wid, int $id): ?array
{
    $st = db()->prepare("SELECT * FROM ad_builds WHERE workspace_id = ? AND id = ?");
    $st->execute([$wid, $id]);
    $r = $st->fetch();
    if (!$r) return null;
    $r['data'] = json_decode((string)$r['data'], true) ?: [];
    $r['progress'] = $r['progress'] ? (json_decode((string)$r['progress'], true) ?: null) : null;
    return $r;
}

function ab_create(int $wid, array $data, string $name): int
{
    $st = db()->prepare("INSERT INTO ad_builds (workspace_id, name, platform, data, created_by) VALUES (?, ?, ?, ?, ?)");
    $st->execute([$wid, mb_substr($name, 0, 200), (string)$data['platform'], json_encode($data, JSON_UNESCAPED_UNICODE), (int)(current_user()['id'] ?? 0) ?: null]);
    return (int)db()->lastInsertId();
}

function ab_save(int $wid, int $id, array $data): void
{
    $name = trim((string)($data['campaign']['name'] ?? '')) ?: t('حملة بدون اسم');
    $st = db()->prepare("UPDATE ad_builds SET name = ?, data = ? WHERE workspace_id = ? AND id = ? AND state IN ('draft','failed')");
    $st->execute([mb_substr($name, 0, 200), json_encode($data, JSON_UNESCAPED_UNICODE), $wid, $id]);
}

function ab_set_progress(int $wid, int $id, string $state, ?array $progress): void
{
    $st = db()->prepare("UPDATE ad_builds SET state = ?, progress = ? WHERE workspace_id = ? AND id = ?");
    $st->execute([$state, $progress === null ? null : json_encode($progress, JSON_UNESCAPED_UNICODE), $wid, $id]);
}

/**
 * قدرات كل منصة في زيرنيو (من docs.zernio.com — POST /v1/ads/create و /v1/ads/campaigns و reach-estimate).
 * اللي مش موثق بوضوح مقفول عمدًا عشان ما يظهرش اختيار يفشل وقت الإطلاق.
 */
function ab_caps(string $p): array
{
    $metaCta = ['LEARN_MORE', 'SHOP_NOW', 'SIGN_UP', 'CONTACT_US', 'BOOK_NOW', 'ORDER_NOW', 'GET_QUOTE', 'GET_OFFER', 'BUY_NOW', 'APPLY_NOW', 'SUBSCRIBE', 'DOWNLOAD', 'SEE_MORE', 'SEE_MENU',
        'REGISTER_NOW', 'GET_DIRECTIONS', 'CALL_NOW', 'MESSAGE_PAGE', 'INSTAGRAM_MESSAGE', 'WATCH_VIDEO', 'INSTALL_MOBILE_APP', 'MAKE_AN_APPOINTMENT', 'BOOK_A_CONSULTATION', 'NO_BUTTON'];
    $base = ['goals' => ['traffic', 'awareness', 'engagement'], 'cbo' => false, 'budgetTypes' => ['daily', 'lifetime'], 'bid' => ['LOWEST_COST_WITHOUT_CAP'], 'special' => [],
        'targeting' => ['regions' => true, 'cities' => true, 'excludeCountries' => false, 'interests' => false, 'work' => false, 'audience' => false, 'placements' => [], 'devices' => false],
        'formats' => ['image'], 'multiText' => false, 'ctas' => ['LEARN_MORE'], 'maxHeadline' => 255, 'maxBody' => 0, 'maxAdsPerSet' => 0, 'whatsapp' => false, 'leadForm' => false,
        'reach' => false, 'validate' => false, 'campaignsEndpoint' => true, 'multiAds' => 'adSetId', 'minDaily' => 0, 'notes' => []];
    $c = [
        'meta' => ['goals' => ['traffic', 'engagement', 'awareness', 'video_views', 'lead_generation', 'conversions', 'app_promotion', 'page_likes'], 'cbo' => true,
            'bid' => ['LOWEST_COST_WITHOUT_CAP', 'LOWEST_COST_WITH_BID_CAP', 'COST_CAP', 'LOWEST_COST_WITH_MIN_ROAS'], 'special' => ['HOUSING', 'EMPLOYMENT', 'CREDIT', 'ISSUES_ELECTIONS_POLITICS'],
            'targeting' => ['regions' => true, 'cities' => true, 'excludeCountries' => false, 'interests' => true, 'work' => false, 'audience' => false,
                'placements' => ['facebook_feed', 'facebook_stories', 'facebook_reels', 'instagram_feed', 'instagram_stories', 'instagram_reels', 'messenger', 'audience_network'], 'devices' => false],
            'formats' => ['image', 'video', 'carousel'], 'multiText' => true, 'ctas' => $metaCta, 'maxHeadline' => 255, 'leadForm' => true, 'reach' => true, 'validate' => true, 'multiAds' => 'creatives', 'maxAdsPerSet' => 50],
        'google' => ['goals' => ['traffic', 'awareness', 'engagement'], 'budgetTypes' => ['daily'], 'bid' => ['LOWEST_COST_WITHOUT_CAP', 'COST_CAP', 'LOWEST_COST_WITH_MIN_ROAS'],
            'targeting' => ['regions' => true, 'cities' => true, 'excludeCountries' => false, 'interests' => false, 'work' => false, 'audience' => false, 'placements' => [], 'devices' => false],
            'formats' => ['image'], 'ctas' => ['LEARN_MORE'], 'maxHeadline' => 30, 'maxBody' => 90, 'notes' => ['googleSearch']],
        'linkedin' => ['goals' => ['traffic', 'engagement', 'awareness', 'video_views', 'lead_generation'], 'formats' => ['image', 'video', 'carousel'],
            'ctas' => ['LEARN_MORE', 'SIGN_UP', 'DOWNLOAD', 'SUBSCRIBE', 'REGISTER', 'JOIN', 'ATTEND', 'REQUEST_DEMO', 'VIEW_QUOTE', 'APPLY', 'SEE_MORE', 'SHOP_NOW', 'BUY_NOW'],
            'maxHeadline' => 400, 'leadForm' => true, 'reach' => true, 'minDaily' => 10, 'targeting' => ['regions' => true, 'cities' => false, 'excludeCountries' => false, 'interests' => false, 'work' => false, 'audience' => false, 'placements' => [], 'devices' => false], 'notes' => ['linkedinOrg']],
        'tiktok' => ['goals' => ['traffic', 'engagement', 'awareness', 'video_views', 'lead_generation', 'conversions', 'app_promotion'], 'formats' => ['video'],
            'ctas' => ['LEARN_MORE', 'SHOP_NOW', 'SIGN_UP', 'CONTACT_US', 'DOWNLOAD', 'BOOK_NOW', 'ORDER_NOW', 'APPLY_NOW', 'WATCH_NOW'], 'validate' => true, 'maxAdsPerSet' => 0,
            'targeting' => ['regions' => true, 'cities' => true, 'excludeCountries' => false, 'interests' => false, 'work' => false, 'audience' => false, 'placements' => [], 'devices' => false], 'notes' => ['tiktokVideo', 'tiktokGender']],
        'pinterest' => ['goals' => ['traffic', 'engagement', 'awareness', 'video_views'], 'maxHeadline' => 100, 'maxBody' => 500, 'reach' => true, 'campaignsEndpoint' => false, 'multiAds' => 'none', 'minDaily' => 5,
            'targeting' => ['regions' => true, 'cities' => false, 'excludeCountries' => false, 'interests' => false, 'work' => false, 'audience' => false, 'placements' => [], 'devices' => false], 'notes' => ['oneAdPerSet']],
        'x' => ['goals' => ['traffic', 'engagement', 'awareness', 'video_views', 'app_promotion'], 'maxHeadline' => 70, 'maxBody' => 280, 'reach' => true, 'multiAds' => 'none', 'notes' => ['oneAdPerSet', 'xCard']],
        'reddit' => ['goals' => ['traffic', 'awareness', 'conversions', 'video_views', 'lead_generation'], 'formats' => ['image', 'video', 'carousel'],
            'ctas' => ['LEARN_MORE', 'SHOP_NOW', 'SIGN_UP', 'CONTACT_US', 'DOWNLOAD', 'GET_QUOTE', 'SUBSCRIBE', 'APPLY_NOW', 'BOOK_NOW', 'ORDER_NOW', 'INSTALL_MOBILE_APP'], 'maxHeadline' => 300, 'multiAds' => 'none', 'notes' => ['oneAdPerSet']],
        'openai' => ['goals' => ['traffic', 'awareness', 'conversions'], 'maxHeadline' => 50, 'maxBody' => 100, 'validate' => true, 'multiAds' => 'none',
            'targeting' => ['regions' => false, 'cities' => false, 'excludeCountries' => false, 'interests' => false, 'work' => false, 'audience' => false, 'placements' => [], 'devices' => false], 'notes' => ['oneAdPerSet', 'countriesOnly']],
        'microsoft' => ['goals' => ['traffic'], 'budgetTypes' => ['daily'], 'maxHeadline' => 30, 'maxBody' => 90, 'campaignsEndpoint' => false, 'multiAds' => 'none',
            'targeting' => ['regions' => false, 'cities' => false, 'excludeCountries' => false, 'interests' => false, 'work' => false, 'audience' => false, 'placements' => [], 'devices' => false], 'notes' => ['oneAdPerSet', 'countriesOnly', 'msKeywords']],
    ];
    $r = array_replace_recursive($base, $c[$p] ?? []);
    foreach (['goals', 'budgetTypes', 'bid', 'special', 'formats', 'ctas', 'notes'] as $k) if (isset($c[$p][$k])) $r[$k] = $c[$p][$k]; // القوائم بتتبدل مش بتتدمج
    if (isset($c[$p]['targeting']['placements'])) $r['targeting']['placements'] = $c[$p]['targeting']['placements'];
    if ($r['multiAds'] === 'none') $r['maxAdsPerSet'] = 1;
    return $r;
}

/** نصوص الواجهة */
function ab_strings(): array
{
    return [
        'back' => t('→ المشاريع'), 'untitled' => t('حملة بدون اسم'), 'lockedTag' => t('اتطلقت — للعرض بس'), 'autosave' => t('بيتحفظ لوحده'), 'saving' => t('بيحفظ...'), 'savedAt' => t('اتحفظ %s'), 'saveErr' => t('ما اتحفظش، هنجرب تاني'),
        'campaign' => t('الحملة'), 'adset' => t('المجموعة الإعلانية'), 'ad' => t('الإعلان'), 'setN' => t('مجموعة %s'), 'adN' => t('إعلان %s'), 'addSet' => t('مجموعة إعلانية'), 'addAd' => t('إعلان'), 'duplicate' => t('نسخ'), 'del' => t('امسح'),
        'delQ' => t('تمسح ده؟'), 'copySuffix' => t('(نسخة)'), 'campName' => t('اسم الحملة'), 'goal' => t('الهدف'), 'budgetBid' => t('الميزانية والمزايدة'), 'schedule' => t('المواعيد والفئات الخاصة'),
        'cbo' => t('ميزانية على الحملة (CBO)'), 'abo' => t('ميزانية لكل مجموعة (ABO)'), 'cboHint' => t('المنصة بتوزع الميزانية على المجموعات حسب الأداء. الأنسب لما تكون عارف الجمهور الكسبان.'),
        'aboHint' => t('كل مجموعة ليها ميزانيتها. الأنسب للاختبار: تقارن جماهير بميزانيات متساوية.'), 'aboOnly' => t('المنصة دي الميزانية فيها على مستوى المجموعة.'), 'usesCbo' => t('بتستخدم ميزانية الحملة'),
        'amount' => t('المبلغ'), 'budgetType' => t('نوع الميزانية'), 'daily' => t('يومي'), 'lifetime' => t('إجمالي'), 'bidStrategy' => t('استراتيجية المزايدة'), 'bidAmount' => t('المبلغ المستهدف'), 'roasFloor' => t('أقل عائد مقبول (ROAS)'),
        'bids' => ['LOWEST_COST_WITHOUT_CAP' => t('أقل تكلفة (تلقائي)'), 'LOWEST_COST_WITH_BID_CAP' => t('حد أقصى للمزايدة'), 'COST_CAP' => t('حد أقصى للتكلفة'), 'LOWEST_COST_WITH_MIN_ROAS' => t('حد أدنى للعائد')],
        'bidHints' => ['LOWEST_COST_WITHOUT_CAP' => t('الأسهل والأسرع في التعلّم. ابدأ بيه لو مش متأكد.'), 'LOWEST_COST_WITH_BID_CAP' => t('بيحدد أقصى مبلغ في المزاد. ممكن يقلل الوصول لو الحد قليل.'),
            'COST_CAP' => t('بيحاول يخلي متوسط تكلفة النتيجة تحت الرقم ده.'), 'LOWEST_COST_WITH_MIN_ROAS' => t('للمبيعات: بيصرف بس لما العائد المتوقع فوق الرقم ده. محتاج Pixel ومبيعات مسجلة.')],
        'start' => t('البداية (اختياري)'), 'end' => t('النهاية (مطلوبة للميزانية الإجمالية)'), 'special' => t('فئات خاصة'), 'specialHint' => t('لازم تختارها لو إعلانك عن سكن أو وظائف أو قروض أو سياسة، وإلا المنصة هترفضه.'),
        'specials' => ['HOUSING' => t('سكن وعقارات'), 'EMPLOYMENT' => t('وظائف'), 'CREDIT' => t('قروض وتمويل'), 'ISSUES_ELECTIONS_POLITICS' => t('سياسة وانتخابات')],
        'goals' => ['traffic' => [t('زيارات'), t('ناس تدخل موقعك')], 'engagement' => [t('تفاعل'), t('لايكات وتعليقات ومشاركات')], 'awareness' => [t('انتشار'), t('أكبر عدد يشوف إعلانك')],
            'video_views' => [t('مشاهدات فيديو'), t('ناس تتفرج على الفيديو')], 'lead_generation' => [t('عملاء محتملين'), t('فورم جوه المنصة')], 'conversions' => [t('مبيعات وتحويلات'), t('محتاج Pixel على موقعك')],
            'app_promotion' => [t('تحميل تطبيق'), t('تثبيت تطبيقك')], 'page_likes' => [t('إعجابات الصفحة'), t('متابعين للصفحة')]],
        'setName' => t('اسم المجموعة'), 'locations' => t('الأماكن'), 'people' => t('الجمهور'), 'searchCountry' => t('ابحث عن دولة...'), 'exclude' => t('استبعد دول'),
        'groups' => ['gulf' => t('الخليج'), 'mena' => t('الشرق الأوسط وشمال أفريقيا'), 'levant' => t('الشام والعراق'), 'maghreb' => t('المغرب العربي'), 'eu' => t('أوروبا'), 'na' => t('أمريكا الشمالية')],
        'regionsCities' => t('مناطق ومدن'), 'searchGeo' => t('اكتب اسم مدينة أو منطقة...'), 'geoHint' => t('لو حطيت مدينة، شيل دولتها من فوق عشان المنصة بترفض التداخل.'), 'city' => t('مدينة'), 'region' => t('منطقة'),
        'radiusQ' => t('نصف قطر حوالين المدينة بالكيلو (0 = المدينة نفسها، حتى 80):'), 'ageMin' => t('من سن'), 'ageMax' => t('لحد سن'), 'gender' => t('النوع'), 'gAll' => t('الكل'), 'gMale' => t('رجالة'), 'gFemale' => t('ستات'),
        'interests' => t('الاهتمامات'), 'searchInterest' => t('اكتب اهتمام وابحث (بالإنجليزي غالبًا أدق)...'), 'interestHint' => t('الاهتمامات بتتجاب من المنصة نفسها، والرقم جنبها حجم الجمهور.'),
        'workPositions' => t('المسميات الوظيفية'), 'searchWork' => t('مثلاً: Marketing Manager'), 'customAud' => t('جمهور مخصص'), 'audHint' => t('من صفحة الجماهير.'), 'none' => t('مفيش'),
        'placementsT' => t('أماكن الظهور'), 'plAuto' => t('تلقائي (موصى بيه)'), 'plManual' => t('يدوي'), 'plAutoHint' => t('المنصة بتختار أرخص مكان بيجيب نتايج. اختار يدوي بس لو عندك سبب.'),
        'placementsL' => ['facebook_feed' => t('فيد فيسبوك'), 'facebook_stories' => t('ستوري فيسبوك'), 'facebook_reels' => t('ريلز فيسبوك'), 'instagram_feed' => t('فيد انستجرام'), 'instagram_stories' => t('ستوري انستجرام'), 'instagram_reels' => t('ريلز انستجرام'), 'messenger' => t('ماسنجر'), 'audience_network' => t('شبكة الجمهور')],
        'devices' => t('الأجهزة'), 'devHint' => '', 'dev_mobile' => t('موبايل'), 'dev_desktop' => t('كمبيوتر'),
        'adName' => t('اسم الإعلان'), 'format' => t('الشكل'), 'formats' => ['image' => t('صورة'), 'video' => t('فيديو'), 'carousel' => t('كاروسيل')], 'media' => t('الصورة / الفيديو'),
        'imageUrl' => t('رابط الصورة'), 'imgHint' => t('JPG أو PNG برابط عام. المقاس المربع 1080×1080 بيشتغل في أغلب الأماكن.'), 'videoUrl' => t('رابط الفيديو'), 'thumbUrl' => t('صورة الغلاف'),
        'cardN' => t('كارت %s'), 'addCard' => t('كارت'), 'cardDesc' => t('وصف'), 'copy' => t('النص'), 'headline' => t('العنوان'), 'body' => t('النص الأساسي'), 'bodyHint' => t('أول 125 حرف هي اللي بتبان قبل "عرض المزيد".'),
        'maxChars' => t('حد أقصى %s حرف'), 'moreHeadlines' => t('عناوين إضافية للاختبار'), 'moreBodies' => t('نصوص إضافية للاختبار'), 'variantHint' => t('المنصة بتجرب التوليفات وبتعرض الأحسن لكل شخص.'), 'addVariant' => t('نسخة'),
        'ctaT' => t('زر الإجراء والرابط'), 'cta' => t('زر الإجراء'), 'linkUrl' => t('الرابط'), 'waNumber' => t('رقم واتساب'), 'waHint' => t('لازم يكون مربوط بالصفحة على فيسبوك.'), 'leadForm' => t('رقم فورم العملاء المحتملين'), 'leadFormHint' => t('من صفحة العملاء المحتملين أو مدير الإعلانات.'),
        'ctas' => ['LEARN_MORE' => t('اعرف أكتر'), 'SHOP_NOW' => t('تسوق الآن'), 'SIGN_UP' => t('سجل'), 'CONTACT_US' => t('تواصل معنا'), 'BOOK_NOW' => t('احجز الآن'), 'ORDER_NOW' => t('اطلب الآن'), 'GET_QUOTE' => t('اطلب عرض سعر'),
            'GET_OFFER' => t('احصل على العرض'), 'BUY_NOW' => t('اشتري الآن'), 'APPLY_NOW' => t('قدّم الآن'), 'SUBSCRIBE' => t('اشترك'), 'DOWNLOAD' => t('تحميل'), 'SEE_MORE' => t('شوف المزيد'), 'SEE_MENU' => t('شوف المنيو'), 'REGISTER_NOW' => t('سجل الآن'),
            'GET_DIRECTIONS' => t('الاتجاهات'), 'CALL_NOW' => t('اتصل الآن'), 'MESSAGE_PAGE' => t('رسالة ماسنجر'), 'INSTAGRAM_MESSAGE' => t('رسالة انستجرام'), 'WATCH_VIDEO' => t('شاهد الفيديو'), 'INSTALL_MOBILE_APP' => t('ثبّت التطبيق'),
            'MAKE_AN_APPOINTMENT' => t('احجز موعد'), 'BOOK_A_CONSULTATION' => t('احجز استشارة'), 'NO_BUTTON' => t('من غير زرار'), 'REGISTER' => t('سجل'), 'JOIN' => t('انضم'), 'ATTEND' => t('احضر'), 'REQUEST_DEMO' => t('اطلب عرض توضيحي'),
            'VIEW_QUOTE' => t('شوف العرض'), 'APPLY' => t('قدّم'), 'WATCH_NOW' => t('شاهد الآن')],
        'summary' => t('الملخص'), 'structure' => t('الهيكل'), 'structTxt' => t('%s مجموعة · %s إعلان'), 'dailyTotal' => t('الميزانية اليومية'), 'monthly' => t('تقريبًا في الشهر'),
        'checks' => t('الفحص'), 'nErr' => t('%s لازم يتصلح'), 'allOk' => t('جاهزة'), 'noIssues' => t('مفيش ملاحظات.'), 'noRes' => t('مفيش نتايج'), 'searching' => t('بيدوّر...'), 'searchErr' => t('البحث فشل'),
        'iName' => t('اكتب اسم للحملة'), 'iGoal' => t('اختار هدف تدعمه المنصة'), 'iBudget' => t('حط ميزانية للحملة'), 'iLifeEnd' => t('الميزانية الإجمالية من غير تاريخ نهاية بتخلص بعد 7 أيام'), 'iDates' => t('تاريخ النهاية قبل البداية'),
        'iBid' => t('حط المبلغ المستهدف للمزايدة'), 'iRoas' => t('حط أقل عائد مقبول'), 'iNoSets' => t('ضيف مجموعة إعلانية واحدة على الأقل'), 'iSetBudget' => t('حط ميزانية للمجموعة'), 'iGeo' => t('اختار دولة أو مدينة'),
        'iNarrowAge' => t('السن ضيق جدًا، الإعلان ممكن ما يوصلش'), 'iNoAds' => t('ضيف إعلان واحد على الأقل'), 'iMaxAds' => t('المنصة دي بتقبل %s إعلان بس في المجموعة'), 'iBody' => t('اكتب نص الإعلان'),
        'iHeadLen' => t('العنوان أطول من %s حرف'), 'iImg' => t('حط رابط صورة يبدأ بـ https'), 'iVid' => t('حط رابط الفيديو'), 'iCards' => t('الكاروسيل محتاج كارتين على الأقل'), 'iLink' => t('حط رابط يبدأ بـ https://'),
        'iLeadForm' => t('هدف العملاء المحتملين محتاج رقم فورم'), 'iWa' => t('رقم واتساب لازم يكون بالصيغة الدولية'), 'iRisk' => t('كلمة ممكن تتسبب في رفض الإعلان: "%s"'), 'iOverlap' => t('"%s" و"%s" نفس الجمهور بالظبط، هيتنافسوا على نفس الناس'),
        'riskWords' => ['الأفضل', 'الأرخص', 'أرخص', 'مضمون', '100%', 'هل أنت', 'هل انت', 'guaranteed', 'cheapest', 'best in', 'are you'],
        'ai' => ['build' => t('ابني الحملة بالذكاء الاصطناعي'), 'buildIntro' => t('قولّي عاوز إيه، والذكاء الاصطناعي هيصمم الهيكل كامل: مجموعات بجماهير مختلفة، وفي كل مجموعة إعلانات بزوايا مختلفة، والميزانية مقسمة. بتراجعه قبل ما يتطبق.'),
            'brief' => t('الهدف والتفاصيل'), 'briefPh' => t('مثلاً: عاوز عملاء محتملين لخدمة إدارة السوشيال للشركات الصغيرة في السعودية والإمارات، عرض أول شهر بخصم 20%'), 'product' => t('المنتج أو الخدمة'), 'anyProduct' => t('— من قاعدة المعرفة —'), 'productPh' => t('اسم المنتج'),
            'dailyBudget' => t('الميزانية اليومية (%s)'), 'buildNote' => t('الصور والروابط هتفضل فاضية، ضيفها بعد التطبيق. الاهتمامات بتتجاب من المنصة نفسها.'), 'buildGo' => t('صمّم الحملة'), 'building' => t('بيصمم الهيكل والإعلانات... ممكن ياخد دقيقة'),
            'day' => t('يوم'), 'tips' => t('نصايح التنفيذ'), 'applyNote' => t('التطبيق هيستبدل المجموعات والإعلانات الحالية.'), 'again' => t('عدّل الطلب'), 'apply' => t('طبّق الهيكل'), 'replaceQ' => t('هيتمسح الهيكل الحالي ويتحط الجديد. نكمل؟'),
            'resolving' => t('بيجيب الاهتمامات من المنصة...'), 'applied' => t('اتطبق الهيكل. ضيف الصور والروابط.'), 'audFor' => t('جمهور مقترح لـ "%s"'), 'thinking' => t('بيفكر...'), 'applyAud' => t('طبّق الجمهور'), 'audBtn' => t('اقترح جمهور'),
            'copyT' => t('نسخ مقترحة للإعلان'), 'copyBtn' => t('اكتب نسخ'), 'writing' => t('بيكتب 3 نسخ...'), 'useThis' => t('استخدمها'), 'asNew' => t('كإعلان جديد'),
            'reviewT' => t('راجع الحملة'), 'reviewing' => t('بيراجع الحملة زي ميديا باير...'), 'high' => t('مهم'), 'medium' => t('متوسط'), 'low' => t('بسيط'), 'goThere' => t('روح'),
            'preview' => t('المعاينة'), 'sponsored' => t('مُموَّل'), 'noBody' => t('نص الإعلان هيظهر هنا'), 'noHead' => t('العنوان'), 'combos' => t('المنصة هتجرب %s توليفة من العناوين والنصوص'),
            'reachT' => t('حجم الجمهور المتوقع'), 'reachBtn' => t('احسب من المنصة'), 'estimating' => t('بيحسب...'), 'reachErr' => t('ما قدرناش نحسب'), 'reachNA' => t('المنصة ما رجعتش تقدير للاستهداف ده'), 'people' => t('شخص'),
            'dailyReach' => t('حوالي %s في اليوم'), 'notReady' => t('(تقدير مبدئي)'), 'noReach' => t('المنصة دي مش بتدي تقدير للوصول قبل الإطلاق.'), 'netErr' => t('الاتصال وقع')],
        'launch' => ['title' => t('الإطلاق'), 'stCamp' => t('إنشاء الحملة "%s"'), 'stSet' => t('إنشاء "%s" وأول إعلان'), 'stSetAds' => t('إنشاء "%s" و%s إعلان'), 'stAd' => t('إضافة "%s" لـ "%s"'),
            'pausedNote' => t('كل حاجة بتتعمل متوقفة. بعد ما تراجعها على المنصة، شغّلها من صفحة الحملات.'), 'validate' => t('افحص على المنصة (من غير إنشاء)'), 'validating' => t('بيفحص...'), 'vOk' => t('المنصة قبلتها'),
            'confirm' => t('موافق إن %s خطوة هتتبعت للمنصة وهتعمل الحملة متوقفة.'), 'go' => t('اطلق الحملة (متوقفة)'), 'resume' => t('كمّل الإطلاق من حيث وقف'), 'fixFirst' => t('صلّح الأخطاء الحمرا الأول.'),
            'done' => t('الحملة اتعملت على المنصة ومتوقفة.'), 'openCamps' => t('افتح الحملات'), 'noPerm' => t('الإطلاق لصاحب صلاحية الصرف.'), 'lastQ' => t('هنبعت الحملة للمنصة دلوقتي (متوقفة، من غير صرف). نكمل؟'),
            'unknown' => t('فيه خطوة اتبعتت وما وصلش ردها. اتأكد من صفحة الحملات هي اتعملت ولا لأ.'), 'markRetry' => t('اتأكدت إنها ما اتعملتش، جرّب تاني'), 'markRetryQ' => t('متأكد إنها ما اتعملتش على المنصة؟ لو اتعملت هتتكرر.'),
            'netErr' => t('الاتصال وقع. هنحمّل الصفحة عشان نشوف وصلنا لفين.'), 'skipped' => t('المنصة دي إعلان واحد بس في كل مجموعة: %s إعلان زيادة مش هيتبعت.')],
        'notesT' => ['googleSearch' => t('جوجل: الإعلان بيتعمل إعلان بحث. العنوان حتى 30 حرف والنص حتى 90.'), 'linkedinOrg' => t('لينكدإن: الإعلان بينزل باسم صفحة الشركة، والميزانية أقل حاجة 10 دولار في اليوم.'),
            'tiktokVideo' => t('تيك توك: فيديو بس.'), 'tiktokGender' => t('تيك توك ما بيقبلش استهداف النوع ولا سن تحت 18 من هنا.'), 'oneAdPerSet' => t('المنصة دي إعلان واحد بس في كل مجموعة. اعمل مجموعات أكتر بدل إعلانات أكتر.'),
            'xCard' => t('X: النص حتى 280 حرف، وكارت الموقع محتاج صورة وعنوان ورابط.'), 'countriesOnly' => t('المنصة دي بتستهدف بالدول بس.'), 'msKeywords' => t('مايكروسوفت: إعلان بحث بكلمات مفتاحية.')],
    ];
}

/* ===================== الإطلاق ===================== */

/** خطوات الإطلاق بالترتيب: حملة ← لكل مجموعة: أول إعلان (بيعمل المجموعة) ← باقي الإعلانات */
function ab_plan(array $d): array
{
    $C = ab_caps((string)$d['platform']); $steps = [];
    if ($C['campaignsEndpoint']) $steps[] = ['k' => 'campaign'];
    foreach ($d['adSets'] as $si => $st) {
        if ($C['multiAds'] === 'creatives') { $steps[] = ['k' => 'set', 'si' => $si, 'ads' => count($st['ads'])]; continue; }
        foreach (array_values($st['ads']) as $ai => $ad) {
            if ($C['multiAds'] === 'none' && $ai > 0) break;
            $steps[] = $ai === 0 ? ['k' => 'set', 'si' => $si, 'ai' => 0] : ['k' => 'ad', 'si' => $si, 'ai' => $ai];
        }
    }
    return $steps;
}

/** الاستهداف بصيغة زيرنيو */
function ab_targeting(array $tg, string $p): array
{
    $o = [];
    $geo = array_merge($tg['regions'] ?? [], $tg['cities'] ?? []);
    $coveredCc = array_values(array_unique(array_filter(array_map(function ($g) { return $g['cc'] ?? ''; }, $geo))));
    $cc = array_values(array_diff($tg['countries'] ?? [], $coveredCc)); // ميتا بترفض دولة ومدينة جواها مع بعض
    if ($cc) $o['countries'] = $cc;
    if (!empty($tg['regions'])) $o['regions'] = array_map(function ($g) { return ['key' => (string)$g['key']]; }, $tg['regions']);
    if (!empty($tg['cities'])) $o['cities'] = array_map(function ($g) use ($p) { $x = ['key' => (string)$g['key']]; if ($p === 'meta' && !empty($g['radius'])) { $x['radius'] = (int)$g['radius']; $x['distance_unit'] = 'kilometer'; } return $x; }, $tg['cities']);
    if ($p !== 'openai' && $p !== 'microsoft') {
        $o['ageMin'] = max($p === 'tiktok' ? 18 : 13, (int)($tg['ageMin'] ?? 18)); $o['ageMax'] = min(65, (int)($tg['ageMax'] ?? 65));
        if ($p !== 'tiktok' && ($tg['gender'] ?? 'all') !== 'all') $o['gender'] = $tg['gender'];
    }
    if (!empty($tg['interests'])) $o['interests'] = array_map(function ($x) { return ['id' => (string)$x['id'], 'name' => (string)$x['name']]; }, $tg['interests']);
    if ($p === 'meta' && ($tg['placementsMode'] ?? 'auto') === 'manual' && !empty($tg['placements'])) {
        $pp = ['publisher_platforms' => [], 'facebook_positions' => [], 'instagram_positions' => [], 'messenger_positions' => [], 'audience_network_positions' => []];
        $map = ['facebook_feed' => ['facebook', 'facebook_positions', 'feed'], 'facebook_stories' => ['facebook', 'facebook_positions', 'story'], 'facebook_reels' => ['facebook', 'facebook_positions', 'facebook_reels'],
            'instagram_feed' => ['instagram', 'instagram_positions', 'stream'], 'instagram_stories' => ['instagram', 'instagram_positions', 'story'], 'instagram_reels' => ['instagram', 'instagram_positions', 'reels'],
            'messenger' => ['messenger', 'messenger_positions', 'messenger_home'], 'audience_network' => ['audience_network', 'audience_network_positions', 'classic']];
        foreach ($tg['placements'] as $k) if (isset($map[$k])) { [$pub, $pos, $v] = $map[$k]; $pp['publisher_platforms'][] = $pub; $pp[$pos][] = $v; }
        $pp['publisher_platforms'] = array_values(array_unique($pp['publisher_platforms']));
        $o['placements'] = array_filter($pp);
    }
    return $o;
}

function ab_creative(array $ad, array $C, string $goal, string $p = ''): array
{
    $o = ['name' => (string)($ad['name'] ?? ''), 'body' => (string)$ad['body']];
    if ($ad['headline'] !== '') $o['headline'] = (string)$ad['headline'];
    if (count($C['ctas']) > 1 || $C['ctas'][0] !== 'LEARN_MORE') $o['callToAction'] = (string)$ad['cta']; elseif ($ad['linkUrl'] !== '') $o['callToAction'] = 'LEARN_MORE';
    if ($ad['linkUrl'] !== '') $o['linkUrl'] = (string)$ad['linkUrl'];
    if ($ad['format'] === 'image') $o['imageUrl'] = (string)$ad['imageUrl'];
    if ($ad['format'] === 'video') { if ($p === 'tiktok') $o['imageUrl'] = (string)$ad['videoUrl']; else $o['video'] = array_filter(['url' => (string)$ad['videoUrl'], 'thumbnailUrl' => (string)$ad['thumbnailUrl']]); }
    if ($ad['format'] === 'carousel') $o['carouselCards'] = array_map(function ($c) { return array_filter(['imageUrl' => $c['imageUrl'] ?? '', 'linkUrl' => $c['linkUrl'] ?? '', 'headline' => $c['headline'] ?? '', 'description' => $c['description'] ?? '']); }, $ad['cards'] ?? []);
    if ($C['multiText']) {
        $hs = array_values(array_filter(array_merge([$ad['headline']], $ad['headlines'] ?? []), 'strlen')); $bs = array_values(array_filter(array_merge([$ad['body']], $ad['bodies'] ?? []), 'strlen'));
        if (count($hs) > 1) $o['headlines'] = array_slice($hs, 0, 5); if (count($bs) > 1) $o['bodies'] = array_slice($bs, 0, 5);
    }
    if ($goal === 'lead_generation' && !empty($ad['leadGenFormId'])) $o['leadGenFormId'] = (string)$ad['leadGenFormId'];
    return $o;
}

/** جسم الطلب لخطوة. $ids = ['campaign' => ..., 'sets' => [si => adSetId]] */
function ab_payload(array $d, array $step, array $ids, bool $validate = false): array
{
    $p = (string)$d['platform']; $C = ab_caps($p); $c = $d['campaign']; $cbo = $C['cbo'] && $c['budgetLevel'] === 'campaign';
    $base = ['accountId' => (string)$d['connId'], 'adAccountId' => (string)$d['adAccountId']];
    $bid = function () use ($c, $C) { $o = []; if (count($C['bid']) > 1 && ($c['bidStrategy'] ?? '') !== '' && $c['bidStrategy'] !== 'LOWEST_COST_WITHOUT_CAP') { $o['bidStrategy'] = $c['bidStrategy']; if (in_array($c['bidStrategy'], ['LOWEST_COST_WITH_BID_CAP', 'COST_CAP'], true)) $o['bidAmount'] = (float)$c['bidAmount']; if ($c['bidStrategy'] === 'LOWEST_COST_WITH_MIN_ROAS') $o['roasAverageFloor'] = (float)$c['roasFloor']; } return $o; };
    $dates = array_filter(['startDate' => $c['startDate'] ? date('c', strtotime($c['startDate'])) : '', 'endDate' => $c['endDate'] ? date('c', strtotime($c['endDate'])) : '']);
    if ($step['k'] === 'campaign') {
        $o = $base + ['name' => (string)$c['name'], 'goal' => (string)$c['goal'], 'status' => 'PAUSED'];
        if ($cbo) $o += ['budgetAmount' => (float)$c['budget']['amount'], 'budgetType' => $c['budget']['type']] + $bid();
        if (!empty($c['specialAdCategories'])) $o['specialAdCategories'] = array_values($c['specialAdCategories']);
        if ($p === 'x' || $p === 'openai') $o += ['budgetAmount' => (float)($cbo ? $c['budget']['amount'] : ($d['adSets'][0]['budget']['amount'] ?? 0)), 'budgetType' => $cbo ? $c['budget']['type'] : ($d['adSets'][0]['budget']['type'] ?? 'daily')];
        if ($validate) $o['validateOnly'] = true;
        return $o;
    }
    $st = $d['adSets'][$step['si']];
    if ($step['k'] === 'ad') {
        $o = $base + ['adSetId' => (string)($ids['sets'][$step['si']] ?? ''), 'adStatus' => 'PAUSED', 'adName' => (string)($st['ads'][$step['ai']]['name'] ?? '')] + ab_creative($st['ads'][$step['ai']], $C, $c['goal'], $p);
        unset($o['name']); return $o;
    }
    $setName = trim((string)$st['name']) ?: ($c['name'] . ' - ' . ($step['si'] + 1));
    $o = $base + ['goal' => (string)$c['goal'], 'adSetName' => $setName, 'status' => 'PAUSED', 'campaignStatus' => 'PAUSED', 'adSetStatus' => 'PAUSED', 'adStatus' => 'PAUSED', 'targeting' => ab_targeting($st['targeting'], $p)] + $dates;
    if (!empty($ids['campaign']) && !$validate) $o['existingCampaignId'] = (string)$ids['campaign'];
    else $o['campaignName'] = $C['campaignsEndpoint'] ? (string)$c['name'] : (count($d['adSets']) > 1 ? $c['name'] . ' - ' . $setName : (string)$c['name']);
    if (!$cbo) $o += ['budgetAmount' => (float)$st['budget']['amount'], 'budgetType' => $st['budget']['type']] + $bid();
    elseif (empty($ids['campaign']) || $validate) { $o += ['budgetAmount' => (float)$c['budget']['amount'], 'budgetType' => $c['budget']['type'], 'budgetLevel' => 'campaign'] + $bid(); }
    if (!empty($c['specialAdCategories']) && empty($o['existingCampaignId'])) $o['specialAdCategories'] = array_values($c['specialAdCategories']);
    if ($C['multiAds'] === 'creatives') {
        $o['creatives'] = array_map(function ($ad) use ($C, $c, $p) { return ab_creative($ad, $C, $c['goal'], $p); }, array_values($st['ads']));
    } else {
        $first = ab_creative($st['ads'][0], $C, $c['goal'], $p); $o['adName'] = $first['name']; unset($first['name']); $o += $first;
    }
    if ($validate) $o['validateOnly'] = true;
    return $o;
}

/** يدوّر على id في رد زيرنيو بأي شكل */
function ab_find_id(array $r, array $keys): ?string
{
    foreach ($keys as $k) if (isset($r[$k]) && is_scalar($r[$k]) && (string)$r[$k] !== '') return (string)$r[$k];
    foreach ($r as $v) if (is_array($v)) { $x = ab_find_id($v, $keys); if ($x !== null) return $x; }
    return null;
}

/** تنفيذ خطوة واحدة. بيرجع [ok, رسالة, ids محدّثة] */
function ab_run_step(Zernio $z, array $d, array $step, array $ids): array
{
    $body = ab_payload($d, $step, $ids);
    if ($step['k'] === 'campaign') {
        $r = $z->request('POST', '/v1/ads/campaigns', [], $body);
        $cid = ab_find_id($r, ['platformCampaignId', 'campaignId']) ?? ab_find_id($r['campaign'] ?? [], ['id', '_id']);
        if (!$cid) throw new RuntimeException(t('الحملة اتعملت بس ما رجعش رقمها. افتح صفحة الحملات قبل ما تكمل.'));
        $ids['campaign'] = $cid; return [true, '', $ids, $r];
    }
    $r = $z->request('POST', '/v1/ads/create', [], $body);
    if ($step['k'] === 'set') {
        $sid = ab_find_id($r, ['platformAdSetId', 'adSetId']) ?? ab_find_id($r['adSet'] ?? [], ['id', '_id']);
        if ($sid) $ids['sets'][$step['si']] = $sid;
        if (empty($ids['campaign'])) { $cid = ab_find_id($r, ['platformCampaignId', 'campaignId']); if ($cid && ab_caps((string)$d['platform'])['campaignsEndpoint']) $ids['campaign'] = $cid; }
    }
    $warn = ab_find_id($r, ['warning', 'activation_failed']);
    return [true, (string)$warn, $ids, $r];
}
