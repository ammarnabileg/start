#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""ينسخ لقطات الشاشة (Full View) إلى /UX-AUDIT مرقّمة بترتيب رحلة المستخدم."""
import os, shutil

ROOT = os.path.dirname(os.path.abspath(__file__))
CUST_OUT = os.path.normpath(os.path.join(ROOT, '..', 'apps', 'customer_app', 'test', 'screenshots', 'out'))
MERCH_OUT = os.path.normpath(os.path.join(ROOT, '..', 'apps', 'merchant_app', 'test', 'screenshots', 'out'))

# (source-basename, descriptive-name)  بترتيب الرحلة الفعلي
CUSTOMER = [
    ('c01_splash', 'Splash'),
    ('c02_welcome', 'Welcome'),
    ('c03_onboarding', 'Onboarding'),
    ('c04_login', 'Login'),
    ('c05_register', 'Register'),
    ('c06_otp', 'OTP'),
    ('c07_forgot', 'Forgot-Password'),
    ('c08_notif_priming', 'Notification-Permission'),
    ('c09_loc_priming', 'Location-Permission'),
    ('c10_qr_home', 'Home-QR'),
    ('c11_stores', 'My-Stores'),
    ('c12_notifications', 'Notifications'),
    ('c13_profile', 'Profile'),
    ('c14_edit_profile', 'Edit-Profile'),
    ('c15_settings', 'Settings'),
    ('c16_store_detail', 'Store-Detail'),
    ('c16b_store_levels', 'Store-Levels'),
    ('store_tab_1_overview', 'Store-Tab-Overview'),
    ('store_tab_2_visits', 'Store-Tab-Visits'),
    ('store_tab_3_points', 'Store-Tab-Points'),
    ('store_tab_4_rewards', 'Store-Tab-Rewards'),
    ('store_tab_5_levels', 'Store-Tab-Levels'),
    ('store_tab_6_coupons', 'Store-Tab-Coupons'),
    ('store_tab_7_questions', 'Store-Tab-Questions'),
    ('store_tab_7b_reviews', 'Store-Tab-Reviews'),
    ('store_tab_8_history', 'Store-Tab-History'),
    ('c17_reward_detail', 'Reward-Detail'),
    ('c18_show_cashier', 'Show-QR-To-Cashier'),
    ('c19_wheel', 'Lucky-Wheel'),
    ('c20_my_prizes', 'My-Prizes'),
    ('c21_prize_qr', 'Prize-QR'),
    ('c22_leaderboard', 'Leaderboard'),
    ('c23_referral', 'Referral'),
    ('c24_deliver_confirm', 'Deliver-Confirm'),
    ('c25_report', 'Report-Form'),
    ('c26_redeemed', 'Redeemed-Success'),
    ('report_chat_real', 'Report-Chat'),
    ('dispute_1_customer', 'Dispute-Customer-View'),
    ('dispute_2_merchant', 'Dispute-Merchant-View'),
    ('dispute_3_admin', 'Dispute-Admin-View'),
    ('dispute_4_jump', 'Dispute-Jump-To-Message'),
]

MERCHANT = [
    ('m01_splash', 'Splash'),
    ('m02_welcome', 'Welcome'),
    ('m03_login', 'Login'),
    ('m04_otp', 'OTP'),
    ('m05_staff_login', 'Staff-Login'),
    ('m06_register_business', 'Register-Business'),
    ('m07_pending', 'Pending-Approval'),
    ('m08_dashboard', 'Dashboard'),
    ('m37_setup_checklist', 'Setup-Checklist'),
    ('m09_scanner', 'Scanner'),
    ('m09b_prize_deliver', 'Prize-Deliver'),
    ('m10_customer_profile', 'Customer-Profile'),
    ('m11_management', 'Management-Hub'),
    ('m12_business_profile', 'Business-Profile'),
    ('m13_edit_business', 'Edit-Business'),
    ('m14_map_picker', 'Map-Picker'),
    ('m15_branches', 'Branches'),
    ('m16_campaigns', 'Visit-Campaigns'),
    ('m16b_campaign_editor', 'Campaign-Editor'),
    ('m17_rewards', 'Rewards'),
    ('m18_levels', 'Levels'),
    ('m18b_levels_editor', 'Level-Editor'),
    ('m19_wheel', 'Lucky-Wheel-Config'),
    ('m20_coupons', 'Coupons'),
    ('m21_questions', 'Questions'),
    ('m22_responses', 'Question-Responses'),
    ('m23_customers', 'Customers'),
    ('m23b_reports', 'Reports'),
    ('m36_report_chat', 'Report-Chat'),
    ('m24_staff', 'Staff'),
    ('m25_roles', 'Roles-Permissions'),
    ('m26_pos', 'POS-Integration'),
    ('m27_store_leaderboard', 'Store-Leaderboard'),
    ('m28_analytics', 'Analytics'),
    ('m29_announcements', 'Announcements'),
    ('m30_settings', 'Settings'),
    ('m30b_scope_switch', 'Scope-Switch'),
    ('m31_plans', 'Subscription-Plans'),
    ('m32_subscription', 'Subscription'),
    ('m33_unavailable', 'Feature-Unavailable'),
    ('activity_log', 'Activity-Log'),
    ('m35_staff_messages', 'Staff-Messages'),
    ('m34_referral_program', 'Referral-Program'),
    ('m1_reviews', 'Reviews'),
    ('m2_reply', 'Review-Reply'),
]


def find(name, dirs):
    for d in dirs:
        p = os.path.join(d, name + '.png')
        if os.path.exists(p):
            return p
    return None


def build(items, dest, dirs):
    if os.path.isdir(dest):
        for f in os.listdir(dest):
            if f.lower().endswith('.png'):
                os.remove(os.path.join(dest, f))
    os.makedirs(dest, exist_ok=True)
    n = 0
    missing = []
    for src, label in items:
        n += 1
        p = find(src, dirs)
        out = os.path.join(dest, f'{n:03d}-{label}.png')
        if p:
            shutil.copyfile(p, out)
        else:
            missing.append(src)
    return n, missing


cn, cm = build(CUSTOMER, os.path.join(ROOT, 'Customer-App'), [CUST_OUT])
mn, mm = build(MERCHANT, os.path.join(ROOT, 'Merchant-App'), [MERCH_OUT, CUST_OUT])
print(f'Customer-App: {cn} screens' + (f'  MISSING={cm}' if cm else ''))
print(f'Merchant-App: {mn} screens' + (f'  MISSING={mm}' if mm else ''))
