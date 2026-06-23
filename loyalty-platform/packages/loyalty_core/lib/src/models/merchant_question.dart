enum QuestionType {
  singleChoice,
  multiChoice,
  text;

  static QuestionType fromString(String v) => switch (v) {
        'single_choice' => QuestionType.singleChoice,
        'multi_choice' => QuestionType.multiChoice,
        _ => QuestionType.text,
      };

  String get value => switch (this) {
        QuestionType.singleChoice => 'single_choice',
        QuestionType.multiChoice => 'multi_choice',
        QuestionType.text => 'text',
      };

  bool get isChoice => this != QuestionType.text;
}

class QuestionOption {
  final String id;
  final String label;
  final int sortOrder;
  const QuestionOption(
      {required this.id, required this.label, this.sortOrder = 0});

  factory QuestionOption.fromJson(Map<String, dynamic> j) => QuestionOption(
        id: j['id'] as String,
        label: j['label'] as String,
        sortOrder: j['sort_order'] as int? ?? 0,
      );
}

/// سؤال يضيفه التاجر بنقاط مكافأة. متطابق مع public.merchant_questions.
class MerchantQuestion {
  final String id;
  final String merchantId;
  final String title;
  final String? description;
  final QuestionType type;
  final int pointsReward;
  final bool required;
  final bool active;
  final List<QuestionOption> options;
  final bool answeredByMe; // يُحسب من question_responses

  const MerchantQuestion({
    required this.id,
    required this.merchantId,
    required this.title,
    required this.type,
    required this.pointsReward,
    this.description,
    this.required = false,
    this.active = true,
    this.options = const [],
    this.answeredByMe = false,
  });

  factory MerchantQuestion.fromJson(Map<String, dynamic> j) => MerchantQuestion(
        id: j['id'] as String,
        merchantId: j['merchant_id'] as String,
        title: j['title'] as String,
        description: j['description'] as String?,
        type: QuestionType.fromString(j['type'] as String),
        pointsReward: j['points_reward'] as int? ?? 0,
        required: j['required'] as bool? ?? false,
        active: j['active'] as bool? ?? true,
        options: (j['question_options'] as List<dynamic>? ?? [])
            .map((o) => QuestionOption.fromJson(o as Map<String, dynamic>))
            .toList(),
        answeredByMe: j['answered_by_me'] as bool? ?? false,
      );
}
