<?php
// English translations for the external-assistant (MCP) pages, the content-bank/compose/content-plan additions,
// the ad-builder language/dialect picker, and the knowledge company-brief and website-reader strings.
return [
    // connect_ai (ChatGPT / Claude connection)
    'المساعد الخارجي' => 'External Assistant',
    'ربط ChatGPT / Claude' => 'Connect ChatGPT / Claude',
    'اربط ChatGPT أو Claude بمنصتك فيدير العمل من داخل المحادثة: يقرأ حساباتك ومنشوراتك ومخزون المحتوى، ويبدأ خطط محتوى جديدة. ثلاث خطوات:' =>
        'Connect ChatGPT or Claude to your workspace so it can run the work from inside the chat: it reads your accounts, posts, and content bank, and starts new content plans. Three steps:',
    'أنشئ توكنًا' => 'Create a token',
    'هذا توكنك. انسخه الآن، فلن يظهر مرة أخرى:' => 'This is your token. Copy it now, it will not be shown again:',
    'أضِف الرابط في ChatGPT أو Claude' => 'Add the URL in ChatGPT or Claude',
    'أضِف الرابط في تطبيقك' => 'Add the URL in your app',
    'القاعدة واحدة في كل التطبيقات: الصق الرابط أعلاه، وأضِف ترويسة مصادقة: Authorization بقيمة «Bearer» ثم مسافة ثم التوكن. التطبيقات التي تدعم MCP عبر رابط (Streamable HTTP):' =>
        'The rule is the same in every app: paste the URL above, then add an Authorization header set to "Bearer", a space, then the token. Apps that support MCP over a URL (Streamable HTTP):',
    'الإعدادات ← Connectors ← أضف موصّلًا (MCP)، الصق الرابط، وفي المصادقة: Bearer ثم التوكن.' => 'Settings → Connectors → add a connector (MCP), paste the URL, and for authorization: Bearer then the token.',
    'الإعدادات ← Connectors ← Add custom connector، الصق الرابط، والتوكن بصيغة Bearer.' => 'Settings → Connectors → Add custom connector, paste the URL, and give the token as Bearer.',
    'Claude Code (الطرفية)' => 'Claude Code (terminal)',
    'نفّذ: claude mcp add --transport http watad «الرابط» --header "Authorization: Bearer «التوكن»".' => 'Run: claude mcp add --transport http watad "URL" --header "Authorization: Bearer TOKEN".',
    'Settings ← MCP ← Add new server، النوع HTTP، الصق الرابط وأضِف ترويسة Authorization: Bearer «التوكن».' => 'Settings → MCP → Add new server, type HTTP, paste the URL and add an Authorization: Bearer TOKEN header.',
    'Settings ← Cascade ← MCP Servers ← أضف خادمًا بالرابط وترويسة Authorization: Bearer «التوكن».' => 'Settings → Cascade → MCP Servers → add a server with the URL and an Authorization: Bearer TOKEN header.',
    'VS Code (Copilot)' => 'VS Code (Copilot)',
    'لوحة الأوامر ← «MCP: Add Server» ← HTTP ← الصق الرابط وأضِف ترويسة Authorization: Bearer «التوكن».' => 'Command palette → "MCP: Add Server" → HTTP → paste the URL and add an Authorization: Bearer TOKEN header.',
    'Cline (إضافة VS Code)' => 'Cline (VS Code extension)',
    'MCP Servers ← Remote ← الصق الرابط وأضِف ترويسة Authorization: Bearer «التوكن».' => 'MCP Servers → Remote → paste the URL and add an Authorization: Bearer TOKEN header.',
    'في settings.json ضمن context_servers أضِف: {"source":"custom","url":"الرابط","headers":{"Authorization":"Bearer التوكن"}}.' => 'In settings.json under context_servers add: {"source":"custom","url":"URL","headers":{"Authorization":"Bearer TOKEN"}}.',
    'Settings ← AI ← Model Context Protocol ← أضف خادمًا بالرابط والتوكن (Bearer).' => 'Settings → AI → Model Context Protocol → add a server with the URL and the token (Bearer).',
    'عقدة MCP Client ← نوع الاتصال Streamable HTTP ← ضع الرابط وترويسة Authorization: Bearer «التوكن».' => 'MCP Client node → connection type Streamable HTTP → set the URL and an Authorization: Bearer TOKEN header.',
    'من الإعدادات، افتح Connectors ثم أضف موصّلًا جديدًا (MCP)، والصق الرابط. في حقل المصادقة اكتب Bearer ثم مسافة ثم التوكن.' =>
        'In Settings, open Connectors and add a new connector (MCP), then paste the URL. In the authorization field type Bearer, a space, then the token.',
    'استخدمه من داخل المحادثة' => 'Use it from inside the chat',
    'اكتب للمساعد بالعربي أو الإنجليزي، مثلًا:' => 'Write to the assistant in Arabic or English, for example:',
    '«ابدأ خطة محتوى عن عروض رمضان، 10 منشورات».' => '"Start a content plan about Ramadan offers, 10 posts."',
    '«اعرض مخزون المحتوى الجاهز».' => '"Show the ready content bank."',
    '«اعرض آخر منشوراتي وحالتها».' => '"Show my latest posts and their status."',
    'اربط تطبيق ChatGPT أو Claude بمنصتك ليدير العمل من داخل المحادثة: يقرأ حساباتك ومنشوراتك ومخزون المحتوى، ويبدأ خطط محتوى جديدة بالذكاء الاصطناعي.' =>
        'Connect the ChatGPT or Claude app to your workspace so it can run the work from inside the chat. It reads your accounts, posts, and content bank, and starts new AI content plans.',
    'التوكن الجديد. انسخه الآن، فلن يظهر مرة أخرى:' => 'Your new token. Copy it now, it will not be shown again:',
    'أنشئ توكن ربط' => 'Create a connection token',
    'اسم للتمييز' => 'A name to tell it apart',
    'مثال: ChatGPT الخاص بي' => 'e.g. My ChatGPT',
    'الصلاحية' => 'Permission',
    'قراءة فقط (الأكثر أمانًا)' => 'Read only (safest)',
    'قراءة وبدء خطط المحتوى' => 'Read and start content plans',
    'نشر: قراءة وخطط ونشر وجدولة مباشرة' => 'Publish: read, plan, and publish or schedule directly',
    'مدير (خطر): كل ما سبق مع صرف الإعلانات والحذف' => 'Admin (risky): all of the above, plus ad spend and deletion',
    'تنبيه: توكن «مدير» يطلق حملات إعلانية بمصروف فعلي، ويحذف منشورات وخططًا، مباشرةً ودون موافقة. أنشئه عند الحاجة فقط، واحذفه إذا تسرّب. تُسجَّل كل عملية في الأسفل.' =>
        'Warning: an Admin token launches ad campaigns that spend real money, and deletes posts and plans, straight away with no approval. Create one only when you need it, and delete it if it leaks. Every action is logged below.',
    'أنشئ التوكن' => 'Create token',
    'رابط الخادم (MCP)' => 'Server URL (MCP)',
    'من الإعدادات، افتح Connectors ثم أضف موصّلًا جديدًا (MCP)، والصق الرابط أعلاه. في حقل المصادقة ضع الكلمة Bearer ثم مسافة ثم التوكن.' =>
        'In Settings, open Connectors and add a new connector (MCP), then paste the URL above. In the authorization field, type Bearer, a space, then the token.',
    'من الإعدادات، افتح Connectors ثم أضف موصّلًا مخصصًا، والصق الرابط نفسه، والتوكن بصيغة Bearer.' =>
        'In Settings, open Connectors and add a custom connector, paste the same URL, and give the token as Bearer.',
    'بعد الربط اكتب في المحادثة مثلًا: «ابدأ خطة محتوى عن كذا» أو «اعرض مخزون المحتوى».' =>
        'Once connected, type in the chat, for example: "Start a content plan about X" or "Show the content bank".',
    'التوكنات' => 'Tokens',
    'امسح كل التوكنات' => 'Delete all tokens',
    'حذف كل التوكنات؟ سيتوقف أي اتصال مع ChatGPT أو Claude فورًا، ولا يمكن التراجع.' =>
        'Delete all tokens? Any connection to ChatGPT or Claude will stop at once, and this cannot be undone.',
    'لا توجد توكنات بعد.' => 'No tokens yet.',
    'قراءة + كتابة' => 'Read and write',
    'قراءة فقط' => 'Read only',
    'آخر استخدام %s' => 'Last used %s',
    'لم يُستخدم' => 'Never used',
    'حذف التوكن؟ سيتوقف أي اتصال يستخدمه.' => 'Delete this token? Any connection using it will stop.',
    'سجل النشر عبر المساعد' => 'Assistant publishing log',
    'لا توجد عمليات بعد.' => 'No actions yet.',
    'أُنشئ توكن جديد. انسخه الآن، فلن يظهر مرة أخرى.' => 'A new token was created. Copy it now, it will not be shown again.',
    'حُذفت كل التوكنات (%s). سيتوقف أي اتصال قائم فورًا.' => 'All tokens were deleted (%s). Any live connection will stop at once.',
    'حُذف التوكن.' => 'Token deleted.',

    // compose: pull from content bank
    'اسحب من المخزون' => 'Pull from the bank',
    'اسحب منشورًا من المخزون' => 'Pull a post from the bank',
    'ابحث في المحتوى…' => 'Search the content…',
    'استخدم النص الطويل' => 'Use the long text',
    'النص القصير' => 'Short text',
    'جارٍ التحميل…' => 'Loading…',
    'حصلت مشكلة. حاول تاني.' => 'Something went wrong. Try again.',
    'لا نتائج.' => 'No results.',
    'المخزون فارغ. اعتمد منشورات من «إنشاء المحتوى».' => 'The bank is empty. Approve posts from Create Content.',
    'ملف' => 'file',
    'تم سحب المنشور إلى المحرر.' => 'The post was pulled into the editor.',

    // content bank + plan
    'المنصات ذات الحد القصير (X وما شابهها) تأخذ النص القصير، وباقي المنصات تأخذ النص الطويل (لينكدإن).' =>
        'Platforms with a short limit (X and similar) use the short text, and the rest use the long text (LinkedIn).',
    'محتوى جديد' => 'New content',
    'اضبط اللوجو' => 'Adjust the logo',
    'ضبط اللوجو على هذه الصورة' => 'Adjust the logo on this image',
    'تم تحديث اللوجو على الصورة.' => 'The logo on the image was updated.',
    'لا يوجد لوجو في ملف الشركة.' => 'There is no logo in the company profile.',
    'جارٍ إعادة التركيب…' => 'Recompositing…',

    // ad builder language/dialect
    'الفصحى' => 'Standard Arabic',
    'بيضاء' => 'Neutral Arabic',
    'مغاربية' => 'Maghrebi',
    'اكتب اللهجة أو الأسلوب' => 'Type the dialect or style',

    // knowledge: company brief
    'ملخص تلقائي' => 'Auto summary',
    'يُستخدم دائمًا في الردود' => 'Always used in replies',
    'مثبّت' => 'Pinned',
    'يتحدّث تلقائيًا مع أي تعديل في قاعدة المعرفة، وغير قابل للإيقاف أو التعديل' =>
        'Updates automatically with any change to the knowledge base, and cannot be turned off or edited',

    // ad builder launch guard (surfaced via admin tool / page)
    'الحملة بدأ إطلاقها على المنصة. لا يمكن حذف المشروع حتى لا يضيع سجل ما تم إنشاؤه.' =>
        'The campaign has started launching on the platform. The project cannot be deleted so the record of what was created is not lost.',

    // knowledge: website reader + reading progress (previously untranslated)
    'ابحث في الصفحات…' => 'Search the pages…',
    'عرض الصفحات المقروءة' => 'Show the pages read',
    'إيقاف القراءة' => 'Stop reading',
    'استكمال من آخر نقطة' => 'Resume from the last point',
    'جارٍ بدء القراءة…' => 'Starting the read…',
    'جارٍ قراءة الموقع كله…' => 'Reading the whole site…',
    'قراءة الصور… %s من %s' => 'Reading images… %s of %s',
    'قُرئت %s صفحة · %s حرف' => '%s pages read · %s characters',
    'قُرئت %s صفحة' => '%s pages read',
    'انقطع الاتصال، سنكمل تلقائيًا… %s من %s' => 'The connection dropped, we will continue automatically… %s of %s',
    '%s صفحة تعذّرت قراءتها وتم تخطيها.' => '%s pages could not be read and were skipped.',
    'أُضيف نص الموقع إلى ملفات الشركة.' => 'The site text was added to the company files.',
    'أُوقفت القراءة. هذه نتيجة ما قُرئ حتى الآن، وأُضيف نصه إلى ملفات الشركة.' =>
        'Reading was stopped. This is what was read so far, and its text was added to the company files.',
    'تعذّرت القراءة. اختر الملف نفسه مرة أخرى لتكمل من آخر صفحة حُفظت.' =>
        'Reading failed. Choose the same file again to continue from the last saved page.',
    'كل ما قُرئ محفوظ؛ لو انقطع الاتصال أو أُغلق الجهاز تجده هنا وتكمل القراءة من حيث توقفت.' =>
        'Everything read is saved. If the connection drops or the device is closed, you will find it here and continue from where you stopped.',
    'نقرأ كل صفحات الموقع (حتى %s صفحة) من خريطة الموقع والروابط الداخلية، دون ذكاء اصطناعي ودون استهلاك أي رصيد. يمكنك إغلاق النافذة؛ القراءة تستمر في الخلفية، وتجد النتيجة عند فتحها مرة أخرى.' =>
        'We read every page of the site (up to %s pages) from the sitemap and internal links, with no AI and no credit used. You can close the window; reading continues in the background, and the result is here when you open it again.',
    'نقرأ موقع الشركة كله، حتى 10,000 صفحة، من خريطة الموقع والروابط الداخلية، ونستخرج منها البيانات: من نحن، والتواصل، والخدمات، والأسعار، والأسئلة الشائعة. بدون ذكاء اصطناعي وبدون استهلاك أي رصيد. تراجع وتختار قبل حفظ أي شيء.' =>
        'We read the whole company site, up to 10,000 pages, from the sitemap and internal links, and pull out the data: about, contact, services, prices, and FAQs. No AI and no credit used. You review and choose before anything is saved.',
    'أي حجم، حتى 1 جيجا وأكثر — ويُقرأ النص الموجود على الصور' => 'Any size, 1 GB and up, and text inside images is read',
    'اربط مزوّد ذكاء اصطناعي أولًا.' => 'Connect an AI provider first.',
    'لا تملك صلاحية إنشاء المنشورات. اطلبها من مالك الشركة.' => 'You do not have permission to create posts. Ask the workspace owner.',
    'المنشور ده لم يعد مسودة. حدّث الصفحة.' => 'This post is no longer a draft. Refresh the page.',
    "مثال:\nأي سعر غير موجود في قائمة الخدمات\nمواعيد توصيل محددة\nالكلام عن المنافسين\nخصومات غير معلنة" =>
        "e.g.\nAny price not in the services list\nSpecific delivery dates\nTalking about competitors\nUnannounced discounts",

    // dialect examples (content plan) — previously untranslated
    'مثال: نساعد فرق المشتريات على تقليل وقت التوريد دون التضحية بالجودة.' => 'e.g. We help procurement teams cut lead time without giving up quality.',
    'مثال: نساعد فريق المشتريات يختصر وقت التوريد بدون ما يضحّي بالجودة.' => 'e.g. We help the procurement team shorten lead time without giving up quality.',
    'مثال: نساعد فريق المشتريات يختصر وقت التوريد من دون ما يتأثر المستوى.' => 'e.g. We help the procurement team shorten lead time without affecting quality.',
    'مثال: نساعد فريق المشتريات يختصر وقت التوريد بدون ما تتأثر الجودة.' => 'e.g. We help the procurement team shorten lead time without quality dropping.',
    'مثال: نساعد فريق المشتريات يختصر وقت التوريد بدون ما تنزل الجودة.' => 'e.g. We help the procurement team shorten lead time without quality falling.',
    'مثال: بنساعد فريق المشتريات يختصر وقت التوريد من غير ما الجودة تتأثر.' => 'e.g. We help the procurement team shorten lead time without quality being affected.',
    'مثال: منساعد فريق المشتريات يختصر وقت التوريد بدون ما تتأثر الجودة.' => 'e.g. We help the procurement team shorten lead time without quality being affected.',
    'مثال: نعاونو فريق المشتريات باش يختصر وقت التوريد بلا ما تنقص الجودة.' => 'e.g. We help the procurement team shorten lead time without quality dropping.',
];
