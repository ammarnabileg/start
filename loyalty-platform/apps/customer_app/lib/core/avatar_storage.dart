import 'package:image_picker/image_picker.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// رفع الصورة الشخصية إلى Supabase Storage (bucket: avatars).
class AvatarStorage {
  AvatarStorage._();

  /// يفتح المعرض، يرفع الصورة المختارة، ويعيد الرابط العام (أو null عند الفشل).
  static Future<String?> pickAndUpload() async {
    try {
      final uid = Supabase.instance.client.auth.currentUser?.id;
      if (uid == null) return null;

      final x = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        maxWidth: 600,
        imageQuality: 80,
      );
      if (x == null) return null;

      final bytes = await x.readAsBytes();
      final path = '$uid/avatar_${DateTime.now().millisecondsSinceEpoch}.jpg';
      final client = Supabase.instance.client;
      await client.storage.from('avatars').uploadBinary(
            path,
            bytes,
            fileOptions: const FileOptions(
              contentType: 'image/jpeg',
              upsert: true,
            ),
          );
      return client.storage.from('avatars').getPublicUrl(path);
    } catch (_) {
      return null;
    }
  }
}
