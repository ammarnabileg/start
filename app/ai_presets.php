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
