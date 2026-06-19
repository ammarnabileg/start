#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""يجمّع لقطات الشاشة في أوراق A4 قابلة للطباعة (٦ شاشات في كل ورقة)."""
import os
from PIL import Image, ImageDraw, ImageFont

HERE = os.path.dirname(os.path.abspath(__file__))
CUST_OUT = os.path.join(HERE, 'out')
MERCH_OUT = os.path.normpath(os.path.join(HERE, '..', '..', '..', 'merchant_app', 'test', 'screenshots', 'out'))
FONTS = os.path.join(HERE, 'fonts')
DEST = os.path.join(CUST_OUT, 'sheets')
os.makedirs(DEST, exist_ok=True)

# A4 بورتريه عند 150 نقطة/بوصة
PAGE_W, PAGE_H = 1240, 1754
COLS, ROWS = 3, 2
PER = COLS * ROWS
TILE_W, TILE_H = 356, 772
HGAP, VGAP = 50, 22
LABEL_H = 32
MARGIN_L = 36
TOP = 96
BG = (255, 255, 255)
INK = (17, 24, 39)
SUB = (107, 114, 128)
BORDER = (209, 213, 219)
ACCENT = (245, 158, 11)

f_title = ImageFont.truetype(os.path.join(FONTS, 'Tajawal-Bold.ttf'), 34)
f_page = ImageFont.truetype(os.path.join(FONTS, 'Tajawal-Regular.ttf'), 22)
f_label = ImageFont.truetype(os.path.join(FONTS, 'Tajawal-Bold.ttf'), 20)
f_badge = ImageFont.truetype(os.path.join(FONTS, 'Tajawal-Bold.ttf'), 18)


def draw_centered(d, cx, y, text, font, fill):
    # raqm يتكفّل بتشكيل العربية وترتيبها RTL
    w = d.textlength(text, font=font, direction='rtl')
    d.text((cx - w / 2, y), text, font=font, fill=fill, direction='rtl')


# (filename, caption)  — بالترتيب من أول الفلو لآخره
CUSTOMER = [
    ('c01_splash', 'شاشة البداية'),
    ('c02_welcome', 'الترحيب'),
    ('c03_onboarding', 'الجولة التعريفية'),
    ('c04_login', 'تسجيل الدخول'),
    ('c05_register', 'إنشاء حساب'),
    ('c06_otp', 'رمز التحقق (OTP)'),
    ('c07_forgot', 'استعادة كلمة المرور'),
    ('c08_notif_priming', 'إذن الإشعارات'),
    ('c09_loc_priming', 'إذن الموقع'),
    ('c10_qr_home', 'الرئيسية — رمز QR'),
    ('c11_stores', 'متاجري'),
    ('c12_notifications', 'الإشعارات'),
    ('c13_profile', 'الملف الشخصي'),
    ('c14_edit_profile', 'تعديل الملف'),
    ('c15_settings', 'الإعدادات'),
    ('store_tab_1_overview', 'المتجر · نظرة عامة'),
    ('store_tab_2_visits', 'المتجر · الزيارات'),
    ('store_tab_3_points', 'المتجر · النقاط'),
    ('store_tab_4_rewards', 'المتجر · المكافآت'),
    ('store_tab_5_levels', 'المتجر · المستويات'),
    ('store_tab_6_coupons', 'المتجر · الكوبونات'),
    ('store_tab_7_questions', 'المتجر · الأسئلة'),
    ('store_tab_7b_reviews', 'المتجر · التقييمات'),
    ('store_tab_8_history', 'المتجر · السجل'),
    ('c17_reward_detail', 'تفاصيل المكافأة'),
    ('c18_show_cashier', 'عرض QR للكاشير'),
    ('c19_wheel', 'عجلة الحظ'),
    ('c20_my_prizes', 'جوائزي'),
    ('c21_prize_qr', 'رمز QR للجائزة'),
    ('c22_leaderboard', 'لوحة الصدارة'),
    ('c23_referral', 'الإحالة'),
    ('c24_deliver_confirm', 'تأكيد التسليم'),
    ('c25_report', 'إرسال بلاغ'),
    ('c26_redeemed', 'تم الاستبدال'),
    ('report_chat_real', 'محادثة البلاغ'),
    ('dispute_1_customer', 'النزاع · العميل'),
    ('dispute_2_merchant', 'النزاع · التاجر'),
    ('dispute_3_admin', 'النزاع · الأدمن'),
    ('dispute_4_jump', 'النزاع · قفز للرسالة'),
]

MERCHANT = [
    ('m01_splash', 'شاشة البداية'),
    ('m02_welcome', 'الترحيب'),
    ('m03_login', 'تسجيل الدخول'),
    ('m04_otp', 'رمز التحقق (OTP)'),
    ('m05_staff_login', 'دخول الموظفين'),
    ('m06_register_business', 'تسجيل متجر جديد'),
    ('m07_pending', 'بانتظار الموافقة'),
    ('m08_dashboard', 'لوحة التحكم'),
    ('m37_setup_checklist', 'جهّز متجرك'),
    ('m09_scanner', 'الماسح الضوئي'),
    ('m09b_prize_deliver', 'تسليم جائزة'),
    ('m10_customer_profile', 'ملف العميل'),
    ('m11_management', 'مركز الإدارة'),
    ('m12_business_profile', 'ملف المتجر'),
    ('m13_edit_business', 'تعديل المتجر'),
    ('m14_map_picker', 'اختيار الموقع'),
    ('m15_branches', 'الفروع'),
    ('m16_campaigns', 'حملات الزيارة'),
    ('m16b_campaign_editor', 'محرر الحملة'),
    ('m17_rewards', 'المكافآت'),
    ('m18_levels', 'المستويات'),
    ('m18b_levels_editor', 'محرر المستوى'),
    ('m19_wheel', 'إعداد عجلة الحظ'),
    ('m20_coupons', 'الكوبونات'),
    ('m21_questions', 'الأسئلة'),
    ('m22_responses', 'ردود العملاء'),
    ('m23_customers', 'العملاء'),
    ('m23b_reports', 'البلاغات'),
    ('m36_report_chat', 'محادثة البلاغ (٣ أطراف)'),
    ('m24_staff', 'الموظفون'),
    ('m25_roles', 'الأدوار والصلاحيات'),
    ('m26_pos', 'تكامل POS'),
    ('m27_store_leaderboard', 'صدارة المتجر'),
    ('m28_analytics', 'التحليلات'),
    ('m29_announcements', 'الإعلانات'),
    ('m30_settings', 'الإعدادات'),
    ('m30b_scope_switch', 'تبديل النطاق'),
    ('m31_plans', 'الباقات'),
    ('m32_subscription', 'الاشتراك'),
    ('m33_unavailable', 'ميزة غير متاحة'),
    ('activity_log', 'سجل النشاط — مين عمل إيه'),
    ('m35_staff_messages', 'سجل رسائل الموظفين'),
    ('m34_referral_program', 'برنامج الإحالة'),
    ('m1_reviews', 'التقييمات'),
    ('m2_reply', 'الرد على تقييم'),
]


def find(name, dirs):
    for d in dirs:
        p = os.path.join(d, name + '.png')
        if os.path.exists(p):
            return p
    return None


def build(section_ar, items, dirs, prefix):
    pages = [items[i:i + PER] for i in range(0, len(items), PER)]
    npages = len(pages)
    made = []
    n = 0
    for pi, page in enumerate(pages, 1):
        img = Image.new('RGB', (PAGE_W, PAGE_H), BG)
        d = ImageDraw.Draw(img)
        # شريط العنوان
        d.rectangle([0, 0, PAGE_W, 70], fill=(249, 250, 251))
        d.line([0, 70, PAGE_W, 70], fill=BORDER, width=1)
        d.rectangle([0, 0, 8, 70], fill=ACCENT)
        pw_t = d.textlength(section_ar, font=f_title, direction='rtl')
        d.text((MARGIN_L + pw_t, 16), section_ar, font=f_title, fill=INK,
               direction='rtl', anchor='ra')
        pg = f'ورقة {pi} من {npages}'
        pw = d.textlength(pg, font=f_page, direction='rtl')
        d.text((PAGE_W - MARGIN_L, 26), pg, font=f_page, fill=SUB,
               direction='rtl', anchor='ra')

        for idx, (fname, cap) in enumerate(page):
            n += 1
            r, c = divmod(idx, COLS)
            x = MARGIN_L + c * (TILE_W + HGAP)
            y = TOP + r * (TILE_H + LABEL_H + VGAP)
            src = find(fname, dirs)
            if src:
                ph = Image.open(src).convert('RGB').resize((TILE_W, TILE_H), Image.LANCZOS)
                img.paste(ph, (x, y))
            else:
                d.rectangle([x, y, x + TILE_W, y + TILE_H], fill=(243, 244, 246))
                draw_centered(d, x + TILE_W / 2, y + TILE_H / 2, 'غير متوفرة', f_label, SUB)
            d.rectangle([x, y, x + TILE_W, y + TILE_H], outline=BORDER, width=1)
            # شارة الترقيم
            bw, bh = 34, 26
            d.rectangle([x, y, x + bw, y + bh], fill=ACCENT)
            d.text((x + 7, y + 3), str(n), font=f_badge, fill=(255, 255, 255))
            # التسمية
            draw_centered(d, x + TILE_W / 2, y + TILE_H + 6, cap, f_label, INK)

        out = os.path.join(DEST, f'{prefix}_{pi:02d}.png')
        img.save(out, 'PNG')
        made.append(out)
        print('  ', os.path.basename(out))
    return made


print('== تطبيق العميل ==')
cust = build('تطبيق العميل — الفلو الكامل', CUSTOMER, [CUST_OUT], 'sheet_customer')
print('== تطبيق التاجر ==')
merch = build('تطبيق التاجر — الفلو الكامل', MERCHANT, [MERCH_OUT, CUST_OUT], 'sheet_merchant')

# PDF موحّد لكل تطبيق (سهل الطباعة)
def to_pdf(paths, name):
    if not paths:
        return
    imgs = [Image.open(p).convert('RGB') for p in paths]
    out = os.path.join(DEST, name)
    imgs[0].save(out, save_all=True, append_images=imgs[1:], resolution=150.0)
    print('  PDF:', os.path.basename(out))

to_pdf(cust, 'customer_flow_A4.pdf')
to_pdf(merch, 'merchant_flow_A4.pdf')
print('تم. المجلد:', DEST)
