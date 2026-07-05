import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

import '../supabase_providers.dart';

/// مستودع الأسئلة وخياراتها وردودها.
class QuestionsRepository {
  QuestionsRepository(this._client);
  final SupabaseClient _client;

  Future<List<Map<String, dynamic>>> fetchQuestions(String merchantId) async {
    final rows = await _client
        .from('merchant_questions')
        .select('*, question_options(*)')
        .eq('merchant_id', merchantId)
        .order('created_at');
    return List<Map<String, dynamic>>.from(rows);
  }

  Future<Map<String, dynamic>> fetchQuestion(String questionId) {
    return _client
        .from('merchant_questions')
        .select('*, question_options(*)')
        .eq('id', questionId)
        .single();
  }

  Future<List<Map<String, dynamic>>> fetchResponses(String questionId) async {
    final rows = await _client
        .from('question_responses')
        .select('answer_text, selected_option_ids, branch_id, created_at')
        .eq('question_id', questionId)
        .order('created_at', ascending: false);
    return List<Map<String, dynamic>>.from(rows);
  }

  /// إدراج سؤال جديد ويرجّع معرّفه.
  Future<String> insertQuestion(Map<String, dynamic> payload) async {
    final inserted = await _client
        .from('merchant_questions')
        .insert(payload)
        .select('id')
        .single();
    return inserted['id'] as String;
  }

  Future<void> updateQuestion(String id, Map<String, dynamic> payload) {
    return _client.from('merchant_questions').update(payload).eq('id', id);
  }

  Future<void> deleteOptions(String questionId) {
    return _client
        .from('question_options')
        .delete()
        .eq('question_id', questionId);
  }

  Future<void> insertOptions(List<Map<String, dynamic>> options) {
    return _client.from('question_options').insert(options);
  }
}

final questionsRepoProvider = Provider<QuestionsRepository>(
    (ref) => QuestionsRepository(ref.read(supabaseClientProvider)));
