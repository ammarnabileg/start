import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// عميل Supabase المشترك — يُحقن في المستودعات (Repositories).
final supabaseClientProvider =
    Provider<SupabaseClient>((ref) => Supabase.instance.client);
