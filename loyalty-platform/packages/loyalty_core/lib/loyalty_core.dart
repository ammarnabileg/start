/// Barrel file for the shared loyalty_core package.
library loyalty_core;

// Theme
export 'src/theme/app_colors.dart';
export 'src/theme/app_typography.dart';
export 'src/theme/app_theme.dart';
export 'src/theme/app_spacing.dart';
export 'src/theme/responsive.dart';

// Config
export 'src/config/env.dart';

// QR
export 'src/qr/qr_token.dart';

// Logic
export 'src/logic/loyalty_rules.dart';

// Utils
export 'src/utils/idempotency.dart';

// Models
export 'src/models/app_user.dart';
export 'src/models/user_store.dart';
export 'src/models/reward.dart';
export 'src/models/loyalty_level.dart';
export 'src/models/stamp_campaign.dart';
export 'src/models/merchant_settings.dart';
export 'src/models/merchant_question.dart';
export 'src/models/leaderboard_entry.dart';
export 'src/models/merchant_role.dart';
export 'src/models/lucky_wheel.dart';
export 'src/models/user_prize.dart';
export 'src/models/pos_api_key.dart';

// Widgets
export 'src/widgets/app_icon.dart';
export 'src/widgets/app_icon_badge.dart';
export 'src/widgets/primary_button.dart';
export 'src/widgets/app_card.dart';
export 'src/widgets/state_views.dart';
export 'src/widgets/points_badge.dart';
export 'src/widgets/section_header.dart';
export 'src/widgets/stat_card.dart';
export 'src/widgets/hero_header.dart';
export 'src/widgets/feedback.dart';
export 'src/widgets/app_bottom_nav.dart';
export 'src/widgets/paginated_list_view.dart';
export 'src/widgets/levels_journey.dart';
export 'src/widgets/stamp_card.dart';
export 'src/widgets/lucky_wheel_view.dart';
