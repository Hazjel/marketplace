class ImageHelper {
  /// Sanitizes image URLs for Android Emulator compatibility.
  /// Replaces 'localhost' and '127.0.0.1' with '10.0.2.2'.
  static String? sanitizeUrl(String? url) {
    if (url == null || url.isEmpty) return null;

    // Normalize to string in case it's not
    String sanitized = url.toString();

    // Check if running on Android Emulator (logic simplified for now, as 10.0.2.2 works on emulator and won't hurt device if not localhost)
    // In a real app, you might check Platform.isAndroid, but this text replacement is safe enough for this context to fix the specific issue.

    if (sanitized.contains('localhost')) {
      sanitized = sanitized.replaceAll('localhost', '10.0.2.2');
    } else if (sanitized.contains('127.0.0.1')) {
      sanitized = sanitized.replaceAll('127.0.0.1', '10.0.2.2');
    }

    return sanitized;
  }
}
