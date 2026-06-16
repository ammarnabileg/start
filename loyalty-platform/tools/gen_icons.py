#!/usr/bin/env python3
"""Generate the Hatchy SVG icon set + Dart IconData->asset registry.

Consistent style: 24x24 grid, fill none, stroke currentColor, 2px, round caps.
Unmapped Material icons fall back to the built-in Icon at runtime (no breakage).
"""
import os

OUT_SVG = "packages/loyalty_core/assets/icons"
OUT_DART = "packages/loyalty_core/lib/src/widgets/app_icon_registry.dart"

WRAP = ('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
        'stroke="#000" stroke-width="2" stroke-linecap="round" '
        'stroke-linejoin="round">{}</svg>')

# --- base glyphs (key -> inner svg) ---
G = {
 "gift": '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8"/><path d="M12 8v13"/><path d="M12 8S11 3 8 3 6 8 12 8Z"/><path d="M12 8s1-5 4-5 2 5-4 5Z"/>',
 "medal": '<circle cx="12" cy="9" r="5"/><path d="M9 13.4 7.3 21 12 18l4.7 3-1.7-7.6"/>',
 "trophy": '<path d="M7 4h10v5a5 5 0 0 1-10 0V4Z"/><path d="M7 5H4v1a3 3 0 0 0 3 3"/><path d="M17 5h3v1a3 3 0 0 1-3 3"/><path d="M10 14v3M14 14v3"/><path d="M8 20h8"/>',
 "star": '<path d="M12 3l2.6 5.3 5.9.9-4.2 4.1 1 5.8L12 16.9 6.7 19.1l1-5.8L3.5 9.2l5.9-.9L12 3Z"/>',
 "plus": '<path d="M12 5v14M5 12h14"/>',
 "minus": '<path d="M5 12h14"/>',
 "store": '<path d="M4 9 5 5h14l1 4"/><path d="M4 9a2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0"/><path d="M5 11v9h14v-9"/><path d="M9 20v-5h5v5"/>',
 "chevL": '<path d="M15 6l-6 6 6 6"/>',
 "chevR": '<path d="M9 6l6 6-6 6"/>',
 "chevD": '<path d="M6 9l6 6 6-6"/>',
 "check": '<path d="M5 12l5 5L19 7"/>',
 "checkCircle": '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-5"/>',
 "checkBox": '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 12l3 3 5-5"/>',
 "radio": '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.5" fill="#000"/>',
 "refresh": '<path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/><path d="M3 21v-5h5"/>',
 "chart": '<path d="M4 5v14h16"/><path d="M8 15l3-4 3 2 4-6"/>',
 "bars": '<path d="M4 4v16h16"/><path d="M8 18v-5M12 18v-9M16 18v-6"/>',
 "leaderboard": '<path d="M4 20v-8h4v8M10 20V6h4v14M16 20v-6h4v6"/>',
 "ticket": '<path d="M4 7a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v2a2 2 0 0 0 0 4v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-2a2 2 0 0 0 0-4V7Z"/><path d="M14 6v12"/>',
 "card": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
 "wallet": '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18"/><circle cx="17" cy="14" r="1.1" fill="#000"/>',
 "dice": '<rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="9" cy="9" r="1.1" fill="#000"/><circle cx="15" cy="15" r="1.1" fill="#000"/><circle cx="12" cy="12" r="1.1" fill="#000"/>',
 "eye": '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
 "eyeOff": '<path d="M3 3l18 18"/><path d="M10.6 6.1A11 11 0 0 1 12 6c6.5 0 10 6 10 6a17 17 0 0 1-3 3.6"/><path d="M6 7.4A16 16 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 4-.8"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
 "headset": '<path d="M4 13a8 8 0 0 1 16 0"/><rect x="3" y="13" width="3" height="6" rx="1.2"/><rect x="18" y="13" width="3" height="6" rx="1.2"/><path d="M20 19a3 3 0 0 1-3 3h-3"/>',
 "help": '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.2a2.5 2.5 0 1 1 3.4 2.4c-.8.4-1.1.9-1.1 1.7"/><circle cx="11.8" cy="16.5" r=".7" fill="#000"/>',
 "qr": '<rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><path d="M14 14h3v3M20 14v6h-6"/>',
 "qrScan": '<path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3"/><path d="M4 12h16"/>',
 "person": '<circle cx="12" cy="8" r="4"/><path d="M4 20a8 8 0 0 1 16 0"/>',
 "people": '<circle cx="9" cy="8" r="3.2"/><path d="M3 19a6 6 0 0 1 12 0"/><path d="M16 5.2a3.2 3.2 0 0 1 0 5.6"/><path d="M21 19a6 6 0 0 0-3.5-5.5"/>',
 "personAdd": '<circle cx="9" cy="8" r="3.4"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M18 8v6M15 11h6"/>',
 "badge": '<rect x="4" y="4" width="16" height="16" rx="3"/><circle cx="12" cy="10" r="2.4"/><path d="M8 17a4 4 0 0 1 8 0"/>',
 "bell": '<path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6Z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
 "lock": '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
 "key": '<circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M16 4l3 3M14 6l2 2"/>',
 "keyOff": '<path d="M3 3l18 18"/><circle cx="8" cy="15" r="4"/><path d="M11 12l8-8M16 4l3 3"/>',
 "image": '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.6"/><path d="M21 16l-5-5L6 19"/>',
 "camera": '<path d="M4 8h3l1.5-2h7L17 8h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13" r="3.4"/>',
 "upload": '<path d="M12 16V4"/><path d="M7 9l5-5 5 5"/><path d="M5 20h14"/>',
 "edit": '<path d="M4 20h4L19 9l-4-4L4 16v4Z"/><path d="M14 6l4 4"/>',
 "copy": '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M15 9V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4"/>',
 "clock": '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l3 2"/><path d="M9 2h6"/>',
 "clockOff": '<circle cx="12" cy="13" r="8"/><path d="M12 9v4"/><path d="M4 5l16 16"/>',
 "hourglass": '<path d="M7 4h10M7 20h10"/><path d="M7 4c0 4 5 5 5 8s-5 4-5 8"/><path d="M17 4c0 4-5 5-5 8s5 4 5 8"/>',
 "calendar": '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 9h16M8 3v4M16 3v4"/>',
 "calCheck": '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 9h16M8 3v4M16 3v4"/><path d="M9 14l2 2 4-4"/>',
 "calBusy": '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 9h16M8 3v4M16 3v4"/><path d="M10 13l4 4M14 13l-4 4"/>',
 "gear": '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
 "sliders": '<path d="M4 7h9M17 7h3M4 17h3M11 17h9"/><circle cx="15" cy="7" r="2"/><circle cx="9" cy="17" r="2"/>',
 "message": '<path d="M4 5h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 3v-3H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/>',
 "mail": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
 "phone": '<path d="M5 4h4l2 5-3 2a12 12 0 0 0 5 5l2-3 5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
 "map": '<path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14M15 6v14"/>',
 "pin": '<path d="M12 21s7-6 7-11a7 7 0 0 0-14 0c0 5 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/>',
 "logout": '<path d="M10 4H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
 "info": '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="7.8" r=".7" fill="#000"/>',
 "inbox": '<path d="M4 13l2-7h12l2 7v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-5Z"/><path d="M4 13h4a2 2 0 0 0 8 0h4"/>',
 "history": '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/><path d="M3.5 9.5 6 8 4.5 5.5"/>',
 "trash": '<path d="M4 7h16"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/><path d="M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13"/><path d="M10 11v6M14 11v6"/>',
 "megaphone": '<path d="M4 10v4a1 1 0 0 0 1 1h3l8 4V5L8 9H5a1 1 0 0 0-1 1Z"/><path d="M19 9a3 3 0 0 1 0 6"/>',
 "cake": '<path d="M4 21h16v-7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v7Z"/><path d="M4 16c2 0 2 1.4 4 1.4S10 16 12 16s2 1.4 4 1.4S20 16 20 16"/><path d="M12 8V5"/><circle cx="12" cy="4" r=".7" fill="#000"/>',
 "egg": '<path d="M12 3c4 0 7 6 7 10a7 7 0 0 1-14 0c0-4 3-10 7-10Z"/>',
 "shield": '<path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6l8-3Z"/>',
 "shieldCheck": '<path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6l8-3Z"/><path d="M9 12l2 2 4-4"/>',
 "warning": '<path d="M12 4l9 16H3L12 4Z"/><path d="M12 10v4"/><circle cx="12" cy="17" r=".7" fill="#000"/>',
 "xCircle": '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
 "block": '<circle cx="12" cy="12" r="9"/><path d="M6 6l12 12"/>',
 "cloudOff": '<path d="M3 3l18 18"/><path d="M7 16a4 4 0 0 1-.5-8A6 6 0 0 1 17 7"/><path d="M19.5 16.5A3 3 0 0 0 18 11"/>',
 "register": '<rect x="4" y="9" width="16" height="11" rx="1"/><path d="M7 9V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v4"/><path d="M8 13h3M8 16h8"/>',
 "globe": '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18Z"/>',
 "search": '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
 "send": '<path d="M21 3 3 11l7 2 2 7 9-17Z"/><path d="M10 13l4-4"/>',
 "share": '<circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M8.2 11 16 6.8M8.2 13 16 17.2"/>',
 "rocket": '<path d="M9 15l-3-3c2-7 7-9 12-9 0 5-2 10-9 12Z"/><circle cx="14.5" cy="9.5" r="1.5"/><path d="M5 15c-1 2-1 4-1 4s2 0 4-1"/>',
 "bulb": '<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 1 4 10c-.7.7-1 1.3-1 2H9c0-.7-.3-1.3-1-2A6 6 0 0 1 12 3Z"/>',
 "coffee": '<path d="M3 8h14v4a6 6 0 0 1-12 0V8Z"/><path d="M17 9h2a2 2 0 0 1 0 4h-2"/><path d="M6 3c0 1-1 1-1 2M10 3c0 1-1 1-1 2M14 3c0 1-1 1-1 2"/><path d="M3 21h16"/>',
 "quote": '<path d="M6 17c2-1 3-3 3-6V7H4v5h3"/><path d="M16 17c2-1 3-3 3-6V7h-5v5h3"/>',
 "bolt": '<path d="M13 2 4 14h7l-2 8 9-12h-7l2-8Z"/>',
 "doc": '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h6M9 16h4"/>',
 "shortText": '<path d="M5 8h14M5 13h10"/>',
 "dashboard": '<rect x="4" y="4" width="7" height="7" rx="1.2"/><rect x="13" y="4" width="7" height="7" rx="1.2"/><rect x="4" y="13" width="7" height="7" rx="1.2"/><rect x="13" y="13" width="7" height="7" rx="1.2"/>',
 "today": '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 9h16M8 3v4M16 3v4"/><circle cx="12" cy="15" r="1.4" fill="#000"/>',
 "category": '<path d="M12 3l4 6H8l4-6Z"/><circle cx="7" cy="16" r="3.2"/><rect x="14" y="13" width="6" height="6" rx="1.2"/>',
 "trending": '<path d="M4 16l5-5 3 3 7-7"/><path d="M16 7h4v4"/>',
 "block_user": '<circle cx="10" cy="8" r="3.4"/><path d="M3 20a7 7 0 0 1 11-5.7"/><circle cx="17.5" cy="16.5" r="3.5"/><path d="M15 14l5 5"/>',
}

# --- Material IconData name -> glyph key ---
M = {
 "card_giftcard_rounded":"gift","card_giftcard_outlined":"gift","card_giftcard":"gift","redeem_rounded":"gift",
 "workspace_premium_outlined":"medal","workspace_premium_rounded":"medal","military_tech_rounded":"medal","badge_outlined":"badge","badge_rounded":"badge",
 "emoji_events_rounded":"trophy","emoji_events_outlined":"trophy",
 "add":"plus","add_rounded":"plus","add_circle_outline_rounded":"plus","add_business_rounded":"store","add_a_photo_outlined":"camera","person_add_alt_1_rounded":"personAdd","group_add_outlined":"personAdd",
 "remove_rounded":"minus","remove_circle_outline":"minus",
 "storefront_rounded":"store","storefront_outlined":"store","storefront":"store","store_mall_directory_outlined":"store",
 "chevron_left_rounded":"chevL","chevron_left":"chevL","keyboard_arrow_down_rounded":"chevD",
 "check_rounded":"check","check":"check","check_circle":"checkCircle","check_circle_rounded":"checkCircle","check_circle_outline":"checkCircle","check_box_rounded":"checkBox","radio_button_checked_rounded":"radio",
 "star_rounded":"star","stars_rounded":"star",
 "repeat_rounded":"refresh","event_repeat_rounded":"refresh","replay_rounded":"refresh","refresh":"refresh",
 "insights_rounded":"chart","trending_up_rounded":"trending","bar_chart_rounded":"bars","leaderboard_rounded":"leaderboard",
 "confirmation_num_outlined":"ticket","confirmation_number_rounded":"ticket","credit_card_outlined":"card",
 "account_balance_wallet_outlined":"wallet",
 "casino_rounded":"dice","casino_outlined":"dice",
 "visibility_outlined":"eye","visibility":"eye","visibility_off_outlined":"eyeOff","visibility_off":"eyeOff",
 "support_agent_outlined":"headset","quiz_outlined":"help","help_outline_rounded":"help","info_outline":"info","info_outline_rounded":"info",
 "qr_code_2_rounded":"qr","qr_code_scanner_rounded":"qrScan",
 "person_outline":"person","person_rounded":"person","person_outline_rounded":"person",
 "people_alt_rounded":"people","groups_2_outlined":"people","groups_rounded":"people","group_outlined":"people",
 "notifications_none_rounded":"bell","notifications_rounded":"bell","notifications_active_rounded":"bell","notifications_active_outlined":"bell",
 "lock_outline_rounded":"lock","lock_outline":"lock","vpn_key_rounded":"key","key_off_rounded":"keyOff",
 "image_outlined":"image","photo_library_outlined":"image","camera_alt_outlined":"camera","upload_outlined":"upload",
 "edit_outlined":"edit","copy_rounded":"copy",
 "timer_outlined":"clock","timer_off_outlined":"clockOff","hourglass_top_rounded":"hourglass","history_rounded":"history",
 "today_rounded":"today","date_range_rounded":"calendar","calendar_today":"calendar","calendar_month_outlined":"calendar","event_outlined":"calendar","event":"calendar",
 "event_available_rounded":"calCheck","event_available_outlined":"calCheck","event_busy_rounded":"calBusy",
 "settings_outlined":"gear","tune_rounded":"sliders",
 "sms_outlined":"message","email_outlined":"mail","phone_outlined":"phone",
 "map_outlined":"map","location_on_rounded":"pin","location_on_outlined":"pin","location_on":"pin",
 "logout_rounded":"logout","inbox_outlined":"inbox",
 "delete_outline_rounded":"trash","delete_outline":"trash",
 "campaign_outlined":"megaphone","campaign_rounded":"megaphone",
 "cake_outlined":"cake","egg_alt_rounded":"egg",
 "admin_panel_settings_outlined":"shieldCheck","shield_outlined":"shield","privacy_tip_outlined":"shieldCheck",
 "warning_amber_rounded":"warning","error_outline":"xCircle","cancel_outlined":"xCircle","block_rounded":"block","cloud_off_rounded":"cloudOff",
 "point_of_sale_rounded":"register","language_rounded":"globe","language_outlined":"globe",
 "search_rounded":"search","send_rounded":"send","share_rounded":"share","rocket_launch_rounded":"rocket","rocket_launch_outlined":"rocket",
 "lightbulb_outline":"bulb","local_cafe_outlined":"coffee","format_quote_rounded":"quote","flash_on":"bolt",
 "short_text_rounded":"shortText","assignment_outlined":"doc","category_outlined":"category",
 "dashboard_rounded":"dashboard","dashboard_outlined":"dashboard",
}

os.makedirs(OUT_SVG, exist_ok=True)
# write svg files (one per glyph key)
for key, inner in G.items():
    with open(f"{OUT_SVG}/{key}.svg", "w", encoding="utf-8") as f:
        f.write(WRAP.format(inner))

# write dart registry
lines = [
 "// AUTO-GENERATED by tools/gen_icons.py — do not edit by hand.",
 "// Maps Material [IconData] to a Hatchy SVG asset. Unmapped icons fall back",
 "// to the built-in Icon at runtime (see AppIcon).",
 "import 'package:flutter/material.dart';",
 "",
 "const String kIconAssetDir = 'assets/icons';",
 "",
 "// Not const: IconData overrides == so it can't be a const map key.",
 "final Map<IconData, String> kAppIconAssets = {",
]
for name, key in M.items():
    lines.append(f"  Icons.{name}: '{key}',")
lines.append("};")
with open(OUT_DART, "w", encoding="utf-8") as f:
    f.write("\n".join(lines) + "\n")

print(f"wrote {len(G)} svg glyphs -> {OUT_SVG}")
print(f"mapped {len(M)} Material icons -> {OUT_DART}")
