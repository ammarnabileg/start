import 'dart:typed_data';

import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:image_picker/image_picker.dart';
import 'package:supabase_flutter/supabase_flutter.dart';

/// مساعد رفع الصور إلى Supabase Storage.
/// يختار صورة من المعرض، يضغطها، ثم يرفعها ويرجّع رابطها العام.
class MediaStorage {
  /// يختار صورة ويضغطها ويرفعها إلى [bucket]/[folder].
  /// يرجّع الرابط العام عند النجاح، أو null عند الفشل/الإلغاء.
  static Future<String?> pickAndUpload({
    required String bucket,
    required String folder,
  }) async {
    try {
      final picked = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        maxWidth: 1200,
        imageQuality: 88,
      );
      if (picked == null) return null;

      Uint8List? bytes = await FlutterImageCompress.compressWithFile(
        picked.path,
        quality: 70,
        minWidth: 1000,
      );
      bytes ??= await picked.readAsBytes();

      final path = '$folder/${DateTime.now().millisecondsSinceEpoch}.jpg';
      final client = Supabase.instance.client;
      await client.storage.from(bucket).uploadBinary(
            path,
            bytes,
            fileOptions: const FileOptions(
              contentType: 'image/jpeg',
              upsert: true,
            ),
          );
      return client.storage.from(bucket).getPublicUrl(path);
    } catch (_) {
      return null;
    }
  }
}
