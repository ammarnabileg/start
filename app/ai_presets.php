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
        // النشر الجماعي
        'bulk_check' => ['group' => 'analyze', 'label' => t('فحص ملف CSV قبل الرفع'), 'icon' => '🧾', 'input' => t('الصق محتوى ملف CSV هنا'), 'sys' => "Check this bulk-upload CSV before publishing. Report by row number: missing content, invalid or past dates, duplicate or near-duplicate posts, posts too long for X (280 chars), broken encoding, and repeated wording across rows. End with a one-line verdict. Answer in {lang}."],
        'bulk_generate' => ['group' => 'compose', 'label' => t('توليد منشورات من قائمة'), 'icon' => '📦', 'csv' => true, 'input' => t("الصق قائمة المنتجات أو الأفكار، كل واحد في سطر:\nقهوة تركي - 45 جنيه\nلاتيه - 60 جنيه"), 'sys' => "For each line of the user's list, write a distinct social media post (vary angle and opening, don't repeat phrases). Output ONLY CSV with header: content,date — dates spread daily starting tomorrow at 18:00 in format YYYY-MM-DD HH:MM. Quote every content field with double quotes and escape inner quotes by doubling them. Same language as the list."],
    ];
}
