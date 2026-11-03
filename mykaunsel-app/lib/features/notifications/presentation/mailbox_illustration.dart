import 'package:flutter/material.dart';

/// The empty state picture: a mailbox with a small bird and a speech bubble, drawn in the brand colours (blue bubble, red flag, yellow beak).
/// The same picture is on the web (resources/views/components/empty-state-mailbox.blade.php). Decoration only, so it is hidden from screen readers.
class MailboxIllustration extends StatelessWidget {
  const MailboxIllustration({super.key, this.width = 190});

  final double width;

  @override
  Widget build(BuildContext context) {
    final outline = Theme.of(context).colorScheme.onSurface.withValues(alpha: .85);

    return ExcludeSemantics(
      child: SizedBox(
        width: width,
        height: width * 190 / 230,
        child: CustomPaint(painter: _MailboxPainter(outline)),
      ),
    );
  }
}

class _MailboxPainter extends CustomPainter {
  _MailboxPainter(this.outline);

  final Color outline;

  static const _blue = Color(0xFF0A4F88);
  static const _red = Color(0xFFD62839);
  static const _yellow = Color(0xFFE3AF39);
  static const _grey = Color(0xFFE4E8EF);

  @override
  void paint(Canvas canvas, Size size) {
    canvas.scale(size.width / 230);

    Paint fill(Color c) => Paint()..color = c;
    Paint line([double w = 2.5]) => Paint()
      ..color = outline
      ..style = PaintingStyle.stroke
      ..strokeWidth = w
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;

    // Ground shadow and post.
    canvas.drawOval(Rect.fromCenter(center: const Offset(116, 181), width: 68, height: 9), fill(outline.withValues(alpha: .08)));
    canvas.drawRect(const Rect.fromLTRB(109, 108, 123, 180), fill(_grey));
    canvas.drawLine(const Offset(108, 108), const Offset(108, 180), line());
    canvas.drawLine(const Offset(124, 108), const Offset(124, 180), line());

    // Mailbox body, with a round top.
    final body = RRect.fromLTRBAndCorners(30, 62, 196, 118, topLeft: const Radius.circular(34), topRight: const Radius.circular(40), bottomLeft: const Radius.circular(4), bottomRight: const Radius.circular(4));
    canvas.drawRRect(body, fill(const Color(0xFFF1F3F8)));
    canvas.drawRRect(body, line());

    // Door.
    final door = Path()
      ..moveTo(34, 114)
      ..lineTo(34, 96)
      ..cubicTo(34, 78, 45, 66, 64, 64)
      ..cubicTo(82, 66, 92, 78, 92, 96)
      ..lineTo(92, 114);
    canvas.drawPath(door, line());
    canvas.drawCircle(const Offset(96, 118), 2, fill(outline));

    // Flag.
    canvas.drawCircle(const Offset(150, 90), 7, fill(Colors.white));
    canvas.drawCircle(const Offset(150, 90), 7, line(2.2));
    canvas.drawLine(const Offset(150, 90), const Offset(176, 90), line(5)..color = _red);
    final flag = Path()
      ..moveTo(172, 82)
      ..lineTo(186, 82)
      ..lineTo(186, 98)
      ..lineTo(179, 93)
      ..lineTo(172, 98)
      ..close();
    canvas.drawPath(flag, fill(_red));
    canvas.drawPath(flag, line(2));

    // Bird: body, wing, beak, eye, feet.
    final bird = RRect.fromLTRBAndCorners(82, 42, 120, 79, topLeft: const Radius.circular(20), topRight: const Radius.circular(18), bottomLeft: const Radius.circular(12), bottomRight: const Radius.circular(10));
    canvas.drawRRect(bird, fill(Colors.white));
    canvas.drawRRect(bird, line());
    final wing = Path()
      ..moveTo(84, 58)
      ..cubicTo(76, 59, 70, 64, 67, 72)
      ..cubicTo(73, 76, 81, 77, 89, 73);
    canvas.drawPath(wing, fill(const Color(0xFFEEF1F6)));
    canvas.drawPath(wing, line());
    final beak = Path()
      ..moveTo(118, 49)
      ..lineTo(127, 51)
      ..lineTo(119, 56)
      ..close();
    canvas.drawPath(beak, fill(_yellow));
    canvas.drawPath(beak, line(1.8));
    canvas.drawCircle(const Offset(111, 50), 1.8, fill(outline));
    canvas.drawLine(const Offset(96, 79), const Offset(96, 84), line(2));
    canvas.drawLine(const Offset(104, 79), const Offset(104, 84), line(2));

    // Speech bubble with three dots.
    final bubble = RRect.fromLTRBR(130, 10, 192, 46, const Radius.circular(8));
    final tail = Path()
      ..moveTo(150, 46)
      ..lineTo(138, 56)
      ..lineTo(140, 44)
      ..close();
    canvas.drawRRect(bubble, fill(_blue));
    canvas.drawPath(tail, fill(_blue));
    canvas.drawRRect(bubble, line());
    for (final x in const [147.0, 161.0, 175.0]) {
      canvas.drawCircle(Offset(x, 28), 2.6, fill(Colors.white));
    }
  }

  @override
  bool shouldRepaint(_MailboxPainter old) => old.outline != outline;
}
