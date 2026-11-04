<?php
/**
 * أوامر الذكاء الاصطناعي الجاهزة. أي خانة نص فيها data-ai="..." بيظهر جنبها زرار ✨ بالأوامر دي.
 * group = المهمة (بتحدد الموديل من جدول الموديلات)، mode = replace (يستبدل النص) / append (يضيف آخره) / show (يعرض النتيجة بس)
 * needs = text (محتاج نص في الخانة) / context (محتاج محادثة أو كارت حوالين الخانة) / any
 */
function ai_presets(): array
{
    $same = 'Reply in the same language and dialect as the input text.';
    $only = 'Return ONLY the result, no quotes, no explanations, no preamble.';
    return [
        // كتابة المنشورات
        'improve' => ['group' => 'compose', 'label' => t('تحسين النص'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Improve this social media post: clearer, more engaging, same meaning and facts. Keep length similar. $same $only"],
        'from_idea' => ['group' => 'compose', 'label' => t('اكتب منشورًا من الفكرة'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Write a ready-to-publish social media post from this short idea. Natural tone, at most 3 hashtags and 2 emojis. $same $only"],
        'hashtags' => ['group' => 'compose', 'label' => t('اقتراح هاشتاجات'), 'mode' => 'append', 'needs' => 'text', 'sys' => "Suggest 3 to 6 relevant, commonly used hashtags for this post, in the post's language. Return them on ONE line separated by spaces, nothing else."],
        'tone_formal' => ['group' => 'compose', 'label' => t('نبرة رسمية'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Rewrite this text in a professional, formal tone. Keep facts. $same $only"],
        'tone_friendly' => ['group' => 'compose', 'label' => t('نبرة ودودة'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Rewrite this text in a warm, friendly, conversational tone. Keep facts. $same $only"],
        'tone_excited' => ['group' => 'compose', 'label' => t('نبرة حماسية'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Rewrite this text in an energetic, exciting tone suitable for a promotion. Keep facts. $same $only"],
        'tone_funny' => ['group' => 'compose', 'label' => t('نبرة فكاهية'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Rewrite this text with light, tasteful humor. Keep facts, nothing offensive. $same $only"],
        'shorten' => ['group' => 'compose', 'label' => t('اختصار (أقل من 280 حرف)'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Shorten this text to under 270 characters while keeping the key message and call to action. $same $only"],
        'hook' => ['group' => 'compose', 'label' => t('أول سطر أقوى'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Rewrite ONLY the first line of this post into a stronger attention-grabbing hook; keep the rest exactly as is. $same $only"],
        'cta' => ['group' => 'compose', 'label' => t('إضافة دعوة لاتخاذ إجراء'), 'mode' => 'append', 'needs' => 'text', 'sys' => "Write ONE short call-to-action sentence that fits this post (e.g. order now, message us). $same $only"],
        'translate_en' => ['group' => 'compose', 'label' => t('ترجمة للإنجليزية'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Translate this text to natural English, keeping tone, emojis and hashtags. $only"],
        'translate_ar' => ['group' => 'compose', 'label' => t('ترجمة للعربية'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Translate this text to natural Modern Standard Arabic suitable for social media, keeping tone, emojis and hashtags. $only"],
        'variants' => ['group' => 'compose', 'label' => t('نسخة لكل منصة'), 'mode' => 'show', 'needs' => 'text', 'sys' => "Rewrite this post as separate versions for: X (max 270 chars), LinkedIn (professional), Instagram (with hashtags), Facebook (conversational). Label each version with the platform name on its own line. $same"],
        'three' => ['group' => 'compose', 'label' => t('3 صياغات مختلفة'), 'mode' => 'show', 'needs' => 'text', 'sys' => "Write 3 clearly different versions of this post (different angle each). Number them 1, 2, 3. $same"],
        'thread' => ['group' => 'compose', 'label' => t('تحويل لسلسلة تغريدات'), 'mode' => 'show', 'needs' => 'text', 'sys' => "Turn this text into a numbered X thread; each tweet under 270 characters. $same"],
        // التحليل
        'review' => ['group' => 'analyze', 'label' => t('مراجعة قبل النشر'), 'mode' => 'show', 'needs' => 'text', 'sys' => "Review this post before publishing. List briefly: spelling/grammar mistakes, unclear or contradictory claims, anything that could offend or cause legal/brand risk, and missing call to action. If it's fine, say so in one line. Answer in the post's language."],
        'score' => ['group' => 'analyze', 'label' => t('تقييم المنشور من 10'), 'mode' => 'show', 'needs' => 'text', 'sys' => "Rate this social media post out of 10 for engagement potential. Give the score on the first line as 'X/10', then 3 short bullet reasons and 2 concrete improvements. Answer in the post's language."],
        'summarize' => ['group' => 'analyze', 'label' => t('تلخيص المحادثة'), 'mode' => 'show', 'needs' => 'context', 'sys' => "Summarize this customer conversation in at most 3 short bullet points: what the customer wants, what was already answered, and what is still pending. Answer in the interface language: {lang}."],
        'translate_ctx' => ['group' => 'analyze', 'label' => t('ترجمة المحادثة'), 'mode' => 'show', 'needs' => 'context', 'sys' => "Translate this conversation to {lang}, keeping who said what. Return only the translation."],
        'comments_triage' => ['group' => 'classify', 'label' => t('تصنيف التعليقات'), 'mode' => 'show', 'needs' => 'context', 'sys' => "Classify the comments in this text. Output sections in {lang}: 'Purchase intent' (questions about price/availability, list them), 'Complaints', 'Spam or abusive (consider hiding)', 'Positive'. Quote each comment briefly. Skip empty sections."],
        'triage' => ['group' => 'classify', 'label' => t('تحليل العميل'), 'mode' => 'show', 'needs' => 'context', 'sys' => "Analyze the customer in this conversation. Answer in {lang} with exactly 4 lines: Category (inquiry / complaint / purchase / support / spam), Sentiment (satisfied / neutral / upset / angry), Urgency (low / medium / high), Recommended next action (one sentence)."],
        'extract' => ['group' => 'classify', 'label' => t('استخراج بيانات العميل'), 'mode' => 'show', 'needs' => 'context', 'sys' => "Extract customer details mentioned in this conversation: name, phone, email, city, product of interest, order number. Answer in {lang}, one field per line, write '—' if not mentioned. Never guess."],
        // الردود
        'reply_variants' => ['group' => 'reply', 'label' => t('3 صياغات مختلفة للرد'), 'mode' => 'show', 'needs' => 'any', 'sys' => "Write 3 differently-worded versions of this reply (same meaning) so repeated replies to similar comments don't look automated. If the text is empty, write 3 versions of a friendly reply to the latest comment. Number them. $same"],
        'comment_to_dm' => ['group' => 'reply', 'label' => t('رسالة خاصة لصاحب التعليق'), 'mode' => 'replace', 'needs' => 'any', 'sys' => "Write a short private message to send to someone who commented asking about price or availability: thank them, give the answer from the knowledge base if available, and invite them to continue the chat. $only"],
        'from_image' => ['group' => 'compose', 'label' => t('اكتب منشورًا من الصورة'), 'mode' => 'replace', 'needs' => 'image', 'sys' => "Look at the image and write a ready-to-publish social media post about it for the business (use the knowledge base for product names and prices only if they clearly match). Natural tone, max 3 hashtags. Write in {lang} unless the draft text is in another language. $only"],
        'alt_text' => ['group' => 'compose', 'label' => t('وصف Alt للصورة'), 'mode' => 'show', 'needs' => 'image', 'sys' => "Write concise alt text (under 125 characters) describing this image for accessibility and SEO, in {lang}. $only"],
        'img_prompt' => ['group' => 'compose', 'label' => t('اكتب وصف صورة من النص'), 'mode' => 'show', 'needs' => 'text', 'sys' => "Write ONE detailed English image-generation prompt (subject, setting, lighting, style, composition) for an image that fits this social media post. No text in the image. $only"],
        'polish_reply' => ['group' => 'reply', 'label' => t('تحسين ردي'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Polish this customer service reply: polite, clear, correct spelling, same meaning, similar length. $same $only"],
        'reply_review' => ['group' => 'reply', 'label' => t('رد على التقييم'), 'mode' => 'replace', 'needs' => 'context', 'sys' => "Write a short public reply from the business owner to this customer review. Thank them; if negative, apologize without admitting legal fault, and invite them to contact the business privately. Same language as the review. $only"],
        // كتابة متخصصة
        'sms_short' => ['group' => 'compose', 'label' => t('اختصار لرسالة SMS واحدة'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Rewrite this as a single SMS: under 160 characters if Latin, under 70 characters if Arabic. Keep the key info. $same $only"],
        'wa_template' => ['group' => 'compose', 'label' => t('صياغة قالب واتساب'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Rewrite this as a WhatsApp Business message template likely to be approved by Meta: clear purpose, no misleading or aggressive promotional claims, use {{1}}, {{2}} for variables like customer name or order number, under 1024 characters. $same $only"],
        'wa_about' => ['group' => 'compose', 'label' => t('وصف احترافي'), 'mode' => 'replace', 'needs' => 'any', 'sys' => "Write a professional business description (under 500 characters) based on this text. If empty, write a generic one for the company named in the knowledge base. $same $only"],
        'ad_copy' => ['group' => 'compose', 'label' => t('نص إعلان'), 'mode' => 'show', 'needs' => 'text', 'sys' => "Write 3 short ad copy variations (primary text, under 125 characters each) for this product or offer. Number them. $same"],
        'auto_msg' => ['group' => 'compose', 'label' => t('صياغة رسالة تلقائية'), 'mode' => 'replace', 'needs' => 'text', 'sys' => "Rewrite this automated message to a customer: friendly, short, clear next step. Keep any {{placeholders}} exactly as they are. $same $only"],
    ];
}

/**
 * تحليلات الذكاء الاصطناعي من بيانات الشركة الحقيقية. بتظهر كأزرار في أي عنصر فيه data-ai-insights="..."
 * input = لو محتاج نص من المستخدم (placeholder)، csv = النتيجة ملف CSV
 */
function ai_insights(): array
{
    $tz = 'Times are UTC in the data; convert them to the company timezone ({tz}).';
    $base = "Answer in {lang} using short headings and bullet points with concrete numbers from the data. If the data is missing or too small, say so in one line and suggest what to do instead. Never invent numbers.";
    return [
        // لوحة التحكم
        'dash_summary' => ['group' => 'analyze', 'label' => t('ملخص اليوم'), 'icon' => '📋', 'sys' => "Write a short daily briefing for the business owner: how the last 7 days compare to the previous 7 (with % change), the best post, and the inbox status. Max 6 bullets. $base"],
        'dash_todo' => ['group' => 'analyze', 'label' => t('أهم 3 مهام اليوم'), 'icon' => '✅', 'sys' => "Give exactly 3 prioritized actions for today based on the data (unread messages, weak recent performance, empty schedule, accounts needing reconnection...). Each: one bold title line + one reason line. $base"],
        'dash_anomaly' => ['group' => 'analyze', 'label' => t('هل فيه شيء غير طبيعي؟'), 'icon' => '⚠️', 'sys' => "Look at the daily metrics for unusual drops or spikes (more than ~30% from the recent average). List each anomaly with the date, the metric, the size, and the most likely cause from the data. If nothing unusual, say so. $base"],
        'dash_forecast' => ['group' => 'analyze', 'label' => t('توقع الأسبوع القادم'), 'icon' => '📈', 'sys' => "Estimate next week's impressions and engagement from the recent trend. Give a range (low–high), explain the trend in one line, and state clearly that it is an estimate. $base"],
        // المحتوى والجدولة
        'best_time' => ['group' => 'analyze', 'label' => t('أفضل أوقات النشر'), 'icon' => '⏰', 'sys' => "Recommend the best posting days and hours per platform from the best-times data and top posts. Give a simple weekly table-like list. $tz $base"],
        'content_plan' => ['group' => 'compose', 'label' => t('خطة محتوى'), 'icon' => '🗓️', 'input' => t('مثلاً: خطة أسبوعين لمطعم، 4 منشورات أسبوعيًا'), 'sys' => "Create a content plan based on the user's request, the company knowledge base and what performed best in the data. For each post: date (starting tomorrow), platform, post type, and a ready-to-use draft caption. $base"],
        'occasions' => ['group' => 'compose', 'label' => t('مناسبات قادمة'), 'icon' => '🎉', 'sys' => "List the notable occasions, holidays and shopping seasons in the next 60 days relevant to a business in the region of timezone {tz} (today is in the data). For each: date, why it matters, and one post idea. Mark dates that vary (e.g. lunar holidays) as approximate. $base"],
        'queue_check' => ['group' => 'analyze', 'label' => t('فحص المنشورات المجدولة'), 'icon' => '🔍', 'sys' => "Review the upcoming scheduled posts: find duplicates or near-duplicates, days that are overcrowded or empty, platforms that are neglected, and posts scheduled at weak times. Suggest a better distribution. $tz $base"],
        'revive' => ['group' => 'compose', 'label' => t('إعادة إحياء منشور ناجح'), 'icon' => '♻️', 'sys' => "Pick the best-performing post from the data and write 2 fresh rewrites of it (new hook, same core idea) ready to publish again. Mention which post you picked and its numbers. $base"],
        'post_perf' => ['group' => 'analyze', 'label' => t('لماذا نجحت منشورات وفشلت أخرى؟'), 'icon' => '🧪', 'sys' => "Compare the top posts with the weakest posts: patterns in platform, media type (image/video/text), day/hour, length, topic and tone. Give 3-5 clear lessons and what to do more/less. $tz $base"],
        // التحليلات
        'explain' => ['group' => 'analyze', 'label' => t('اشرح لي الأرقام'), 'icon' => '💡', 'sys' => "Explain the analytics in plain language for a non-technical business owner: what is going well, what is not, and why it matters. Then 3 recommendations. $base"],
        'monthly' => ['group' => 'analyze', 'label' => t('تقرير شهري للإدارة'), 'icon' => '📑', 'sys' => "Write a concise monthly management report for the last 30 days: executive summary (3 lines), key numbers, best content, platforms comparison, inbox activity, risks, and next month's priorities. Formal tone. $base"],
        'platforms' => ['group' => 'analyze', 'label' => t('مقارنة المنصات'), 'icon' => '⚖️', 'sys' => "Compare the platforms: where the audience and engagement are strongest, where effort is wasted, and how to split effort next month (percentages). $base"],
        'goals' => ['group' => 'analyze', 'label' => t('اقترح أهدافًا واقعية'), 'icon' => '🎯', 'sys' => "Suggest 3-5 realistic monthly goals (with target numbers) based on the last 30 days and the trend. Explain each target in one line. $base"],
        'content_types' => ['group' => 'analyze', 'label' => t('أي نوع محتوى ينجح؟'), 'icon' => '🎬', 'sys' => "From the top and weakest posts, which content types (video, image, text) and topics work best per platform. Give a clear recommendation. $base"],
        'faq' => ['group' => 'analyze', 'label' => t('أكثر ما يسأل عنه العملاء'), 'icon' => '❓', 'sys' => "From the recent customer messages, list the most frequent questions/topics (grouped), with a suggested ready answer for each based on the company knowledge base. If the knowledge base lacks an answer, say it should be added. $base"],
        // صفحات بتحلل المحتوى الظاهر فيها (page = بيتبعت نص الصفحة، nodata = من غير بيانات Zernio)
        'kw_suggest' => ['group' => 'analyze', 'label' => t('اقترح كلمات للرد التلقائي'), 'icon' => '🔑', 'sys' => "From the recent customer messages, suggest auto-reply rules: for each, the trigger keywords (comma-separated, include common spelling variants and dialect) and a short reply using the knowledge base. $base"],
        'rule_test' => ['group' => 'analyze', 'label' => t('جرّب الكلمات على الرسائل الحقيقية'), 'icon' => '🧪', 'input' => t('اكتب الكلمات مفصولة بفاصلة: سعر، بكام، price'), 'sys' => "The user gives trigger keywords. Check them against the recent customer messages in the DATA: list which messages would match, which relevant messages would be missed (suggest keywords to add), and possible false matches. $base"],
        'rules_conflict' => ['group' => 'analyze', 'label' => t('فحص تعارض القواعد'), 'icon' => '🔀', 'page' => true, 'nodata' => true, 'sys' => "The PAGE shows the automation rules. Find rules whose keywords overlap or conflict, rules that are too broad, and gaps. Give concrete fixes. Answer in {lang}."],
        'tpl_rejects' => ['group' => 'analyze', 'label' => t('لماذا رُفض القالب؟'), 'icon' => '🛑', 'page' => true, 'nodata' => true, 'sys' => "The PAGE lists WhatsApp message templates with their statuses. For each rejected or paused template, explain the most likely reason according to Meta's template guidelines and give a corrected version. If none are rejected, give tips to keep templates approved. Answer in {lang}."],
        'segments' => ['group' => 'analyze', 'label' => t('قسّم جهات الاتصال لشرائح'), 'icon' => '👥', 'page' => true, 'sys' => "From the contacts shown on the PAGE (tags, names, fields) and the DATA, propose 3-6 useful customer segments, how to identify each (tags/filters), and one message idea per segment. Answer in {lang}."],
        'contacts_clean' => ['group' => 'analyze', 'label' => t('تنظيف جهات الاتصال'), 'icon' => '🧹', 'page' => true, 'nodata' => true, 'sys' => "Review the contacts shown on the PAGE: find likely duplicates (same phone/email/name variants), missing names, invalid phone formats. List them and the suggested fix. Answer in {lang}."],
        'sequence_write' => ['group' => 'compose', 'label' => t('اكتب تسلسل رسائل'), 'icon' => '🔗', 'input' => t('الهدف: مثلاً ترحيب بالعميل الجديد ثم عرض خلال أسبوع'), 'sys' => "Write a WhatsApp message sequence for the user's goal: 3-5 steps, each with the delay after the previous step, and the message text (short, friendly, with {{1}} for the name). Use the knowledge base. Answer in {lang}."],
        'ads_analyze' => ['group' => 'analyze', 'label' => t('حلّل الحملات'), 'icon' => '📣', 'page' => true, 'nodata' => true, 'sys' => "Analyze the ad campaigns shown on the PAGE: which spend without results (high cost per result, low CTR), which perform best, and concrete actions (pause, adjust budget, new creative). Never tell the user you changed anything. Answer in {lang}."],
        'audience' => ['group' => 'analyze', 'label' => t('اقترح الجمهور المستهدف'), 'icon' => '🎯', 'input' => t('صف المنتج أو العرض'), 'sys' => "Suggest 2-3 ad audiences for this product: age range, gender if relevant, interests, locations, and a short reason for each. Use the knowledge base and the business region (timezone {tz}). Answer in {lang}."],
        'leads_score' => ['group' => 'classify', 'label' => t('قيّم العملاء المحتملين'), 'icon' => '⭐', 'page' => true, 'nodata' => true, 'sys' => "Score the leads shown on the PAGE as Hot / Warm / Cold based on the information they gave, with a one-line reason each, and say who to contact first. Answer in {lang}."],
        'leads_followup' => ['group' => 'compose', 'label' => t('رسائل متابعة للعملاء المحتملين'), 'icon' => '✉️', 'page' => true, 'nodata' => true, 'sys' => "For the leads shown on the PAGE, write a short personalized follow-up message for each (use their name and what they asked about). Use the knowledge base for facts. Answer in the leads' language."],
        'reviews_analyze' => ['group' => 'analyze', 'label' => t('حلّل التقييمات'), 'icon' => '⭐', 'page' => true, 'nodata' => true, 'sys' => "Analyze the reviews shown on the PAGE: top recurring complaints, top praised points, average sentiment, and which reviews need an urgent reply. Answer in {lang}."],
        'gmb_posts' => ['group' => 'compose', 'label' => t('منشورات Google Business للأسبوع'), 'icon' => '📍', 'sys' => "Write 3 short Google Business Profile posts for this week (offer, update, event or tip), each under 300 characters with a clear call to action, using the knowledge base and what performed well. Answer in {lang}."],
        'team_perf' => ['group' => 'analyze', 'label' => t('تقرير أداء الفريق'), 'icon' => '🧑‍💼', 'page' => true, 'nodata' => true, 'sys' => "From the team and response data shown on the PAGE, summarize each member's activity and response speed where available, who needs support, and fair recommendations. Do not judge people on missing data. Answer in {lang}."],
        'role_suggest' => ['group' => 'analyze', 'label' => t('اقترح صلاحيات لدور'), 'icon' => '🔐', 'input' => t('صف الوظيفة: مثلاً موظف خدمة عملاء يرد على الرسائل فقط'), 'perms' => true, 'nodata' => true, 'sys' => "From the job description, choose the minimal set of permissions from the PERMISSIONS list. Output the chosen permission names as a bullet list (exactly as written), then one line on what you deliberately excluded and why. Answer in {lang}."],
        'admin_ask' => ['group' => 'analyze', 'label' => t('اسأل عن المنصة'), 'icon' => '🛠️', 'input' => t('مثلاً: أي شركة استهلاكها زاد؟ من لم يدخل منذ شهر؟'), 'admin' => true, 'nodata' => true, 'sys' => "You help the platform administrator. Answer ONLY from the PLATFORM data. Be concise with names and numbers. Answer in {lang}."],
        'help' => ['group' => 'analyze', 'label' => t('كيف أستخدم المنصة؟'), 'icon' => '🧭', 'input' => t('مثلاً: كيف أجدول منشورًا؟ أين أربط واتساب؟'), 'nav' => true, 'nodata' => true, 'sys' => "You are the in-app guide. Using the MENU of the app (sections and pages), explain step by step where to go and what to click to do what the user asks. If the app doesn't seem to support it, say so. Answer in {lang}."],
        'workflow_design' => ['group' => 'analyze', 'label' => t('صمّم Workflow من وصف'), 'icon' => '🧩', 'input' => t('مثلاً: كل عميل جديد يسأل عن السعر نبعت له الكتالوج، وبعد يومين عرض خاص'), 'nodata' => true, 'sys' => "Design an automation for the user's description, as step-by-step instructions the user can build in this app: Trigger (and exact keywords), each Step (action, delay, message text with {{contact.name}} placeholders), and Exit conditions. Then list risks (spamming, wrong triggers). Keep messages short; use the knowledge base for facts. Answer in {lang}."],
        'budget_advice' => ['group' => 'analyze', 'label' => t('توصيات الميزانية'), 'icon' => '💰', 'page' => true, 'nodata' => true, 'sys' => "From the campaigns shown on the PAGE, recommend budget changes per campaign: increase / keep / decrease / pause, the suggested new daily budget, and the reason (cost per result, CTR, spend vs results). State clearly at the top that NOTHING was changed and the user must apply changes manually. Answer in {lang}."],
        'competitors' => ['group' => 'analyze', 'label' => t('قارن بالمنافسين'), 'icon' => '🥊', 'input' => t('الصق معلومات عن المنافسين: أسماء صفحاتهم، أمثلة منشورات، أسعار، عدد متابعين...'), 'sys' => "Compare this business (DATA) with the competitors described by the user: content style, posting frequency, offers and pricing, engagement where given. List what competitors do better, where this business is stronger, and 5 concrete actions. Only use what the user pasted for competitors; never invent competitor numbers. $base"],
        'canned' => ['group' => 'compose', 'label' => t('ردود جاهزة للفريق'), 'icon' => '💬', 'sys' => "From the recent customer messages and the knowledge base, write 6-10 ready-made replies for the most common situations. For each: a short title and the reply text. Mark any reply that needs info missing from the knowledge base. $base"],
        // النشر الجماعي
        'bulk_check' => ['group' => 'analyze', 'label' => t('فحص ملف CSV قبل الرفع'), 'icon' => '🧾', 'input' => t('الصق محتوى ملف CSV هنا'), 'sys' => "Check this bulk-upload CSV before publishing. Report by row number: missing content, invalid or past dates, duplicate or near-duplicate posts, posts too long for X (280 chars), broken encoding, and repeated wording across rows. End with a one-line verdict. Answer in {lang}."],
        'bulk_generate' => ['group' => 'compose', 'label' => t('توليد منشورات من قائمة'), 'icon' => '📦', 'csv' => true, 'input' => t("الصق قائمة المنتجات أو الأفكار، كل واحد في سطر:\nقهوة - %1\$s\nلاتيه - %2\$s", '45 ' . cx('cur'), '60 ' . cx('cur')), 'sys' => "For each line of the user's list, write a distinct social media post (vary angle and opening, don't repeat phrases). Output ONLY CSV with header: content,date — dates spread daily starting tomorrow at 18:00 in format YYYY-MM-DD HH:MM. Quote every content field with double quotes and escape inner quotes by doubling them. Same language as the list."],
    ];
}

/** ميزات خاصة (غير الأوامر والتحليلات) */
function ai_special_features(): array
{
    return [
        'assistant' => [t('المساعد الذكي (المحادثة)'), t('تسأله عن أرقامك من صفحة المساعد والرئيسية.')],
        'reply_btn' => [t('زرار اقتراح رد'), t('في الرسائل والتعليقات.')],
        'classify_btn' => [t('تصنيف الرسائل بزرار'), t('زرار "صنّف الآن" وعلامات العاجل ونية الشراء.')],
        'image' => [t('توليد الصور (Higgsfield)'), t('في صفحة منشور جديد.')],
        'video' => [t('توليد الفيديو (Higgsfield)'), t('فيديو من وصف أو من صورة. أغلى بكثير من الصور.')],
        'vision' => [t('فهم الصور'), t('كتابة المنشور ووصف Alt من الصورة المرفوعة.')],
        'calls' => [t('تفريغ وتلخيص المكالمات'), t('يحتاج ربط OpenAI للتفريغ الصوتي.')],
        'auto_reply' => [t('الرد التلقائي خارج المواعيد'), t('يحتاج تفعيلًا منفصلًا وإعدادات من تبويب الاستهلاك والإعدادات.')],
    ];
}

/** الميزات المقفولة في الشركة دي */
function ai_disabled(?int $wid = null): array
{
    static $c = [];
    $wid = $wid ?? (int)ws('id');
    if (!isset($c[$wid])) { $v = json_decode((string)setting('ai_off_' . $wid, ''), true); $c[$wid] = is_array($v) ? $v : []; }
    return $c[$wid];
}

function ai_feature_on(string $key, ?int $wid = null): bool
{
    return !in_array($key, ai_disabled($wid), true);
}

/** أوامر التحليل السريعة لكل صفحة (بتظهر في لوحة ✨ الجانبية) */
function ai_page_insights(string $page): array
{
    $map = [
        'ad_campaign' => ['ads_analyze', 'budget_advice'],
        'ad_create' => ['audience'],
        'admin' => ['admin_ask'],
        'ads' => ['ads_analyze', 'audience', 'budget_advice'],
        'analytics' => ['explain', 'platforms', 'content_types', 'post_perf', 'monthly', 'competitors'],
        'automations' => ['kw_suggest', 'rule_test', 'rules_conflict', 'workflow_design'],
        'broadcasts' => ['best_time', 'segments'],
        'bulk' => ['bulk_generate', 'bulk_check'],
        'calendar' => ['content_plan', 'occasions', 'best_time', 'queue_check'],
        'contacts' => ['segments', 'contacts_clean'],
        'dashboard' => ['dash_summary', 'dash_todo', 'dash_anomaly', 'dash_forecast', 'help'],
        'gmb_location' => ['gmb_posts'],
        'gmb_reviews' => ['reviews_analyze'],
        'goals' => ['goals'],
        'inbox_analytics' => ['faq', 'canned', 'team_perf'],
        'leads' => ['leads_score', 'leads_followup'],
        'posts' => ['post_perf', 'revive', 'content_types'],
        'queue' => ['queue_check', 'best_time'],
        'reports' => ['monthly'],
        'sequences' => ['sequence_write'],
        'team' => ['team_perf', 'role_suggest'],
        'wa_templates' => ['tpl_rejects'],
        'workflows' => ['kw_suggest', 'rules_conflict', 'workflow_design'],
    ];
    $keys = $map[$page] ?? ['dash_summary', 'help'];
    return array_values(array_filter($keys, function ($k) { return isset(ai_insights()[$k]) && ai_feature_on($k); }));
}

/** أيقونات الأوامر بنفس ستايل أيقونات السايد بار (خط رفيع 1.6) */
function ai_icon(string $key, string $cls = 'w-5 h-5'): string
{
    static $p = [
        'spark' => 'M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15.5l-1.9-4.6L5.5 9l4.6-1.4zM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z',
        'dash_summary' => 'M9 4h6v3H9zM8 5H6a1 1 0 00-1 1v14a1 1 0 001 1h12a1 1 0 001-1V6a1 1 0 00-1-1h-2M9 12h6M9 16h4',
        'dash_todo' => 'M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1zM8 12l3 3 5-6',
        'dash_anomaly' => 'M12 4l9 16H3zM12 10v4M12 17.5v.01',
        'dash_forecast' => 'M3 17l6-6 4 4 8-8M15 7h6v6',
        'best_time' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2',
        'content_plan' => 'M4 6a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1zM4 10h16M8 3v4M16 3v4M8 14h3M8 17h6',
        'occasions' => 'M4 11h16v9H4zM3 7h18v4H3zM12 7v13M12 7C10.5 4 7 4 7 6s3 1 5 1zm0 0c1.5-3 5-3 5-1s-3 1-5 1z',
        'queue_check' => 'M4 6h10M4 12h6M4 18h6M16 16a3 3 0 100-6 3 3 0 000 6zM18.2 15.2L21 18',
        'revive' => 'M4 4v6h6M20 20v-6h-6M5.6 15a7 7 0 0012.3 2M18.4 9A7 7 0 006.1 7',
        'post_perf' => 'M9 3h6M10 3v6l-5 9a2 2 0 002 3h10a2 2 0 002-3l-5-9V3M7.5 15h9',
        'explain' => 'M9 18h6M10 21h4M12 3a6 6 0 00-4 10.5c.7.7 1 1.5 1 2.5h6c0-1 .3-1.8 1-2.5A6 6 0 0012 3z',
        'monthly' => 'M7 3h7l5 5v13H7zM14 3v5h5M10 13h6M10 17h6',
        'platforms' => 'M12 4v16M6 20h12M5 7h14M7 7l-3 6a3 3 0 006 0zM17 7l-3 6a3 3 0 006 0z',
        'goals' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 17a5 5 0 100-10 5 5 0 000 10zM12 13a1 1 0 100-2 1 1 0 000 2z',
        'content_types' => 'M4 5h16v14H4zM8 5v14M16 5v14M4 9h4M4 15h4M16 9h4M16 15h4',
        'faq' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM9.5 9.5a2.5 2.5 0 114 2c-.9.6-1.5 1-1.5 2M12 17h.01',
        'canned' => 'M4 5h12v8H8l-4 3zM20 9v10l-3-2h-6v-2',
        'bulk_generate' => 'M12 3l8 4.5v9L12 21l-8-4.5v-9zM4 7.5l8 4.5 8-4.5M12 12v9',
        'bulk_check' => 'M7 3h7l5 5v13H7zM14 3v5h5M9.5 15l2 2 3.5-4',
        'kw_suggest' => 'M3 12V4h8l10 10-8 8zM7.5 8.5h.01',
        'rule_test' => 'M7 4l12 8-12 8z',
        'rules_conflict' => 'M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5',
        'workflow_design' => 'M4 4h6v6H4zM14 14h6v6h-6zM10 7h4a3 3 0 013 3v4M7 10v4a3 3 0 003 3h4',
        'tpl_rejects' => 'M8 3h8l5 5v8l-5 5H8l-5-5V8zM9.5 9.5l5 5M14.5 9.5l-5 5',
        'segments' => 'M9 11a4 4 0 100-8 4 4 0 000 8zM2 21v-1a6 6 0 0112 0v1M16 3.5a4 4 0 010 7.5M22 21v-1a6 6 0 00-4-5.6',
        'contacts_clean' => 'M16 3l5 5-11 11H5l-2-2zM9 9l6 6M10 21h11',
        'sequence_write' => 'M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1',
        'ads_analyze' => 'M3 11v2a1 1 0 001 1h2l5 4V6L6 10H4a1 1 0 00-1 1zM15 8a5 5 0 010 8M18 5a9 9 0 010 14',
        'audience' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 3v4M12 17v4M3 12h4M17 12h4M12 13a1 1 0 100-2 1 1 0 000 2z',
        'budget_advice' => 'M3 7a2 2 0 012-2h13v4M3 7v10a2 2 0 002 2h15v-5M3 7h17v5h-4a2.5 2.5 0 000 5h4',
        'competitors' => 'M4 20V10M10 20V4M16 20v-8M21 20H3',
        'leads_score' => 'M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z',
        'leads_followup' => 'M3 6h18v12H3zM3 7l9 6 9-6',
        'reviews_analyze' => 'M4 5h16v11H9l-5 4zM12 7.5l.9 1.9 2.1.3-1.5 1.5.4 2.1-1.9-1-1.9 1 .4-2.1-1.5-1.5 2.1-.3z',
        'gmb_posts' => 'M12 21s-7-6.2-7-11.5a7 7 0 1114 0C19 14.8 12 21 12 21zM12 12a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
        'team_perf' => 'M8 8a3 3 0 100-6 3 3 0 000 6zM2 16v-1a5 5 0 019-3M14 20v-4M18 20v-8M22 20v-6',
        'role_suggest' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4',
        'admin_ask' => 'M4 5h16v14H4zM8 10l2 2-2 2M12 14h4',
        'help' => 'M12 21a9 9 0 100-18 9 9 0 000 18zM15.5 8.5l-2 5-5 2 2-5z',
    ];
    return '<svg class="' . $cls . ' shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="' . ($p[$key] ?? $p['spark']) . '"/></svg>';
}

/** التحليلات المفعّلة بالشكل اللي الـ JS محتاجه */
function ai_insights_js(): array
{
    $o = [];
    foreach (ai_insights() as $k => $i) {
        if (!ai_feature_on($k)) continue;
        $o[$k] = ['label' => $i['label'], 'icon' => ai_icon($k, 'w-4 h-4'), 'input' => $i['input'] ?? '', 'csv' => !empty($i['csv']), 'page' => !empty($i['page'])];
    }
    return $o;
}
