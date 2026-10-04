import 'package:flutter/cupertino.dart';
import 'package:flutter/material.dart';

/// Shows a bottom sheet containing a Cupertino scrolling wheel time picker.
/// Allows scrolling through Hour, Minute, and AM/PM with Cancel and Done actions.
Future<TimeOfDay?> showScrollWheelTimePicker(
  BuildContext context, {
  TimeOfDay? initialTime,
  String? title,
  bool use24hFormat = false,
  int minuteInterval = 1,
}) async {
  final now = DateTime.now();
  final initTime = initialTime ?? TimeOfDay.fromDateTime(now);

  DateTime tempDateTime = DateTime(
    now.year,
    now.month,
    now.day,
    initTime.hour,
    initTime.minute,
  );

  return showModalBottomSheet<TimeOfDay>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (BuildContext bottomSheetContext) {
      final theme = Theme.of(bottomSheetContext);
      final isDark = theme.brightness == Brightness.dark;
      final backgroundColor = theme.colorScheme.surface;
      final textColor = theme.colorScheme.onSurface;

      return Container(
        decoration: BoxDecoration(
          color: backgroundColor,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.15),
              blurRadius: 10,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: SafeArea(
          top: false,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    TextButton(
                      onPressed: () =>
                          Navigator.of(bottomSheetContext).pop(null),
                      child: Text(
                        'Cancel',
                        style: TextStyle(
                          color: theme.colorScheme.error,
                          fontSize: 16,
                        ),
                      ),
                    ),
                    Expanded(
                      child: Text(
                        title ?? 'Select Time',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                          color: textColor,
                        ),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                    FilledButton(
                      style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 8),
                      ),
                      onPressed: () {
                        final selectedTime = TimeOfDay(
                          hour: tempDateTime.hour,
                          minute: tempDateTime.minute,
                        );
                        Navigator.of(bottomSheetContext).pop(selectedTime);
                      },
                      child: const Text('Done'),
                    ),
                  ],
                ),
              ),
              const Divider(height: 1),
              SizedBox(
                height: 220,
                child: CupertinoTheme(
                  data: CupertinoThemeData(
                    brightness: isDark ? Brightness.dark : Brightness.light,
                    textTheme: CupertinoTextThemeData(
                      dateTimePickerTextStyle: TextStyle(
                        fontSize: 22,
                        color: textColor,
                      ),
                    ),
                  ),
                  child: CupertinoDatePicker(
                    key: const ValueKey('scroll_wheel_time_picker'),
                    mode: CupertinoDatePickerMode.time,
                    use24hFormat: use24hFormat,
                    minuteInterval: minuteInterval,
                    initialDateTime: tempDateTime,
                    onDateTimeChanged: (DateTime newDateTime) {
                      tempDateTime = newDateTime;
                    },
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    },
  );
}

/// Converts [TimeOfDay] to 24-hour format string 'HH:mm' for backend APIs.
String formatTimeOfDay24(TimeOfDay time) {
  return '${time.hour.toString().padLeft(2, '0')}:${time.minute.toString().padLeft(2, '0')}';
}

/// Converts [TimeOfDay] to user-friendly 12-hour format string 'hh:mm a' (e.g., '09:30 AM').
String formatTimeOfDay12(TimeOfDay time) {
  final hour = time.hourOfPeriod == 0 ? 12 : time.hourOfPeriod;
  final minute = time.minute.toString().padLeft(2, '0');
  final period = time.period == DayPeriod.am ? 'AM' : 'PM';
  return '${hour.toString().padLeft(2, '0')}:$minute $period';
}

/// Parses a time string (e.g. '14:30', '14:30:00', '02:30 PM') into a [TimeOfDay].
TimeOfDay? parseTimeOfDay(String? value) {
  if (value == null) return null;
  final trimmed = value.trim();
  if (trimmed.isEmpty) return null;

  // Check 12-hour format with AM/PM e.g. "02:30 PM"
  final amPmMatch = RegExp(
          r'^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)$',
          caseSensitive: false)
      .firstMatch(trimmed);
  if (amPmMatch != null) {
    var hour = int.parse(amPmMatch.group(1)!);
    final minute = int.parse(amPmMatch.group(2)!);
    final isPm = amPmMatch.group(3)!.toUpperCase() == 'PM';
    if (isPm && hour < 12) hour += 12;
    if (!isPm && hour == 12) hour = 0;
    return TimeOfDay(hour: hour, minute: minute);
  }

  // Check 24-hour format e.g. "14:30" or "14:30:00"
  final match24 =
      RegExp(r'^(\d{1,2}):(\d{2})(?::\d{2})?$').firstMatch(trimmed);
  if (match24 != null) {
    final hour = int.parse(match24.group(1)!);
    final minute = int.parse(match24.group(2)!);
    if (hour >= 0 && hour < 24 && minute >= 0 && minute < 60) {
      return TimeOfDay(hour: hour, minute: minute);
    }
  }

  return null;
}

/// Formats a time string into 12-hour display format 'hh:mm AM/PM'.
String formatTimeString12(String? value, {String placeholder = 'Select time'}) {
  if (value == null || value.trim().isEmpty) return placeholder;
  final parsed = parseTimeOfDay(value);
  if (parsed == null) return value;
  return formatTimeOfDay12(parsed);
}

/// Reusable form field widget that displays a read-only time value
/// and opens the scroll-wheel time picker on tap.
class ScrollWheelTimePickerField extends StatelessWidget {
  const ScrollWheelTimePickerField({
    super.key,
    required this.label,
    required this.value,
    this.required = true,
    this.enabled = true,
    required this.onTap,
    this.validator,
  });

  final String label;
  final String value;
  final bool required;
  final bool enabled;
  final VoidCallback? onTap;
  final String? Function(String?)? validator;

  @override
  Widget build(BuildContext context) {
    final isSelected = value.isNotEmpty && parseTimeOfDay(value) != null;
    final displayTime = isSelected
        ? formatTimeString12(value)
        : (value.isNotEmpty ? value : 'Select ${label.toLowerCase()}');

    return FormField<String>(
      key: ValueKey(value),
      initialValue: isSelected ? value : '',
      validator: validator ??
          (val) => required && (val == null || val.trim().isEmpty)
              ? 'Please select ${label.toLowerCase()}.'
              : null,
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          OutlinedButton.icon(
            onPressed: enabled ? onTap : null,
            icon: const Icon(Icons.access_time_outlined),
            label: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                isSelected ? '$label: $displayTime' : '$label: $displayTime',
              ),
            ),
          ),
          if (state.hasError)
            Padding(
              padding: const EdgeInsets.only(left: 12, top: 4),
              child: Text(
                state.errorText!,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontSize: 12,
                ),
              ),
            ),
        ],
      ),
    );
  }
}
