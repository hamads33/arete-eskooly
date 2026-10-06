import React from 'react';
import {
  ActivityIndicator,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Switch,
  Text,
  TextInput,
  View,
  type TextInputProps,
  type ViewStyle,
} from 'react-native';
import {SafeAreaView} from 'react-native-safe-area-context';
import {colors, radius, shadow, space} from './theme';

export function Screen({
  children,
  scroll = true,
  topInset = false,
}: {
  children: React.ReactNode;
  scroll?: boolean;
  topInset?: boolean;
}) {
  return (
    <SafeAreaView style={styles.screen} edges={topInset ? ['top', 'bottom', 'left', 'right'] : ['bottom', 'left', 'right']}>
      {scroll ? (
        <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
          {children}
        </ScrollView>
      ) : (
        <View style={[styles.scroll, styles.flex]}>{children}</View>
      )}
    </SafeAreaView>
  );
}

/** Logo + wordmark, as in the portal header. */
export function BrandHeader({subtitle = 'SMS GATEWAY', right}: {subtitle?: string; right?: React.ReactNode}) {
  return (
    <View style={styles.brand}>
      <Image source={require('../assets/arete-logo.png')} style={styles.brandLogo} resizeMode="contain" />
      <View style={styles.flex}>
        <Text style={styles.brandName}>ARETE</Text>
        <Text style={styles.brandSub}>{subtitle}</Text>
      </View>
      {right}
    </View>
  );
}

/** "Welcome back." style heading with the burgundy full stop. */
export function Hero({eyebrow, title, subtitle}: {eyebrow?: string; title: string; subtitle?: string}) {
  return (
    <View style={styles.hero}>
      {eyebrow ? <Text style={styles.eyebrow}>{eyebrow}</Text> : null}
      <Text style={styles.heroTitle}>
        {title}
        <Text style={{color: colors.primary}}>.</Text>
      </Text>
      {subtitle ? <Text style={styles.heroSub}>{subtitle}</Text> : null}
    </View>
  );
}

export function Steps({current}: {current: 1 | 2 | 3}) {
  const labels = ['Connect', 'Permissions', 'SIM'];
  return (
    <View style={styles.steps}>
      {labels.map((l, i) => {
        const n = i + 1;
        const done = n < current;
        const active = n === current;
        return (
          <View key={l} style={styles.step}>
            <View style={[styles.stepDot, (done || active) && styles.stepDotOn]}>
              <Text style={[styles.stepNum, (done || active) && styles.stepNumOn]}>{done ? '✓' : n}</Text>
            </View>
            <Text style={[styles.stepLabel, active && styles.stepLabelOn]}>{l}</Text>
            {i < labels.length - 1 ? <View style={[styles.stepLine, done && styles.stepLineOn]} /> : null}
          </View>
        );
      })}
    </View>
  );
}

export function Card({children, style}: {children: React.ReactNode; style?: ViewStyle}) {
  return <View style={[styles.card, style]}>{children}</View>;
}

export function SectionTitle({children}: {children: React.ReactNode}) {
  return <Text style={styles.section}>{children}</Text>;
}

export function Paragraph({children, muted}: {children: React.ReactNode; muted?: boolean}) {
  return <Text style={[styles.paragraph, muted && styles.muted]}>{children}</Text>;
}

export function InfoRow({label, value, valueColor}: {label: string; value: React.ReactNode; valueColor?: string}) {
  return (
    <View style={styles.row}>
      <Text style={styles.rowLabel}>{label}</Text>
      <Text style={[styles.rowValue, valueColor ? {color: valueColor} : null]} numberOfLines={2}>
        {value}
      </Text>
    </View>
  );
}

/** Tappable list row with a chevron, like the portal's menu items. */
export function NavRow({title, subtitle, onPress, last}: {title: string; subtitle?: string; onPress: () => void; last?: boolean}) {
  return (
    <Pressable
      onPress={onPress}
      accessibilityRole="button"
      style={({pressed}) => [styles.navRow, !last && styles.navRowBorder, pressed && {backgroundColor: colors.surfaceSoft}]}>
      <View style={styles.flex}>
        <Text style={styles.navTitle}>{title}</Text>
        {subtitle ? <Text style={styles.navSub}>{subtitle}</Text> : null}
      </View>
      <Text style={styles.chevron}>›</Text>
    </Pressable>
  );
}

type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost';

export function Button({
  title,
  onPress,
  variant = 'primary',
  disabled,
  loading,
  large,
  style,
}: {
  title: string;
  onPress: () => void;
  variant?: ButtonVariant;
  disabled?: boolean;
  loading?: boolean;
  large?: boolean;
  style?: ViewStyle;
}) {
  const filled = variant === 'primary' || variant === 'danger';
  const bg = variant === 'primary' ? colors.primary : variant === 'danger' ? colors.danger : variant === 'secondary' ? colors.card : 'transparent';
  const fg = filled ? colors.primaryText : colors.primary;
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{disabled: !!disabled || !!loading}}
      onPress={onPress}
      disabled={disabled || loading}
      style={({pressed}) => [
        styles.button,
        large && styles.buttonLarge,
        {backgroundColor: bg, opacity: disabled ? 0.45 : pressed ? 0.85 : 1},
        variant === 'secondary' && styles.buttonSecondary,
        filled && !disabled && styles.buttonShadow,
        style,
      ]}>
      {loading ? (
        <ActivityIndicator color={fg} />
      ) : (
        <Text style={[styles.buttonText, large && styles.buttonTextLarge, {color: fg}]}>{title}</Text>
      )}
    </Pressable>
  );
}

export function LinkButton({title, onPress}: {title: string; onPress: () => void}) {
  return (
    <Pressable onPress={onPress} accessibilityRole="button" style={styles.link}>
      <Text style={styles.linkText}>{title}</Text>
    </Pressable>
  );
}

export function Field({
  label,
  help,
  error,
  ...input
}: TextInputProps & {label: string; help?: string; error?: string | null}) {
  const [focused, setFocused] = React.useState(false);
  return (
    <View style={styles.field}>
      <Text style={styles.fieldLabel}>{label}</Text>
      <TextInput
        placeholderTextColor={colors.faint}
        {...input}
        onFocus={e => {
          setFocused(true);
          input.onFocus?.(e);
        }}
        onBlur={e => {
          setFocused(false);
          input.onBlur?.(e);
        }}
        style={[
          styles.input,
          input.multiline && styles.inputMultiline,
          focused && styles.inputFocused,
          error ? styles.inputError : null,
          input.style,
        ]}
      />
      {error ? <Text style={styles.fieldError}>{error}</Text> : help ? <Text style={styles.fieldHelp}>{help}</Text> : null}
    </View>
  );
}

export function SwitchRow({
  label,
  help,
  value,
  onValueChange,
  disabled,
}: {
  label: string;
  help?: string;
  value: boolean;
  onValueChange: (v: boolean) => void;
  disabled?: boolean;
}) {
  return (
    <View style={styles.switchRow}>
      <View style={styles.flex}>
        <Text style={styles.switchLabel}>{label}</Text>
        {help ? <Text style={styles.fieldHelp}>{help}</Text> : null}
      </View>
      <Switch
        value={value}
        onValueChange={onValueChange}
        disabled={disabled}
        trackColor={{true: colors.primaryLight, false: colors.borderStrong}}
        thumbColor={value ? colors.primary : '#ffffff'}
      />
    </View>
  );
}

export type Tone = 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'brand';

export const toneColors: Record<Tone, {fg: string; bg: string}> = {
  success: {fg: colors.success, bg: colors.successBg},
  warning: {fg: colors.warning, bg: colors.warningBg},
  danger: {fg: colors.danger, bg: colors.dangerBg},
  info: {fg: colors.info, bg: colors.infoBg},
  neutral: {fg: colors.muted, bg: colors.neutralBg},
  brand: {fg: colors.primary, bg: colors.primary50},
};

export function Pill({label, tone}: {label: string; tone: Tone}) {
  const c = toneColors[tone];
  return (
    <View style={[styles.pill, {backgroundColor: c.bg}]}>
      <View style={[styles.dot, {backgroundColor: c.fg}]} />
      <Text style={[styles.pillText, {color: c.fg}]}>{label}</Text>
    </View>
  );
}

export function Banner({
  tone,
  title,
  message,
  actionLabel,
  onAction,
}: {
  tone: Tone;
  title: string;
  message?: string | null;
  actionLabel?: string;
  onAction?: () => void;
}) {
  const c = toneColors[tone];
  return (
    <View style={[styles.banner, {backgroundColor: c.bg}]}>
      <View style={[styles.bannerBar, {backgroundColor: c.fg}]} />
      <View style={styles.flex}>
        <Text style={[styles.bannerTitle, {color: c.fg}]}>{title}</Text>
        {message ? <Text style={styles.bannerText}>{message}</Text> : null}
        {actionLabel && onAction ? (
          <Pressable onPress={onAction} accessibilityRole="button" style={[styles.bannerAction, {borderColor: c.fg}]}>
            <Text style={[styles.bannerActionText, {color: c.fg}]}>{actionLabel}</Text>
          </Pressable>
        ) : null}
      </View>
    </View>
  );
}

export function Tile({label, value, tone}: {label: string; value: string | number; tone?: Tone}) {
  const c = tone ? toneColors[tone] : null;
  return (
    <View style={styles.tile}>
      <Text style={[styles.tileValue, c ? {color: c.fg} : null]}>{value}</Text>
      <Text style={styles.tileLabel}>{label}</Text>
    </View>
  );
}

export function Segmented<T extends string>({
  options,
  value,
  onChange,
}: {
  options: Array<{value: T; label: string}>;
  value: T;
  onChange: (v: T) => void;
}) {
  return (
    <View style={styles.segmented}>
      {options.map(o => (
        <Pressable
          key={o.value}
          onPress={() => onChange(o.value)}
          accessibilityRole="button"
          accessibilityState={{selected: o.value === value}}
          style={[styles.segment, o.value === value && styles.segmentActive]}>
          <Text style={[styles.segmentText, o.value === value && styles.segmentTextActive]}>{o.label}</Text>
        </Pressable>
      ))}
    </View>
  );
}

export function TrustNote({children}: {children: React.ReactNode}) {
  return (
    <View style={styles.trust}>
      <View style={styles.trustDot} />
      <Text style={styles.trustText}>{children}</Text>
    </View>
  );
}

export const styles = StyleSheet.create({
  flex: {flex: 1},
  screen: {flex: 1, backgroundColor: colors.bg},
  scroll: {padding: space.lg, paddingBottom: space.xxl * 2},
  brand: {flexDirection: 'row', alignItems: 'center', gap: space.md, marginBottom: space.lg},
  brandLogo: {width: 44, height: 44},
  brandName: {fontSize: 22, fontWeight: '900', color: colors.primaryDark, letterSpacing: 0.5},
  brandSub: {fontSize: 10, fontWeight: '800', color: colors.muted, letterSpacing: 2},
  hero: {marginBottom: space.lg},
  eyebrow: {fontSize: 11, fontWeight: '800', color: colors.primary, letterSpacing: 1.8, marginBottom: space.sm},
  heroTitle: {fontSize: 30, fontWeight: '900', color: colors.text, letterSpacing: -0.5},
  heroSub: {fontSize: 15, color: colors.muted, marginTop: space.sm, lineHeight: 22},
  steps: {flexDirection: 'row', marginBottom: space.xl},
  step: {flex: 1, alignItems: 'center'},
  stepDot: {
    width: 30,
    height: 30,
    borderRadius: 15,
    borderWidth: 2,
    borderColor: colors.borderStrong,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.card,
    zIndex: 1,
  },
  stepDotOn: {backgroundColor: colors.primary, borderColor: colors.primary},
  stepNum: {fontSize: 13, fontWeight: '800', color: colors.faint},
  stepNumOn: {color: colors.primaryText},
  stepLabel: {fontSize: 12, color: colors.faint, marginTop: 6, fontWeight: '600'},
  stepLabelOn: {color: colors.primary, fontWeight: '800'},
  stepLine: {position: 'absolute', top: 14, left: '65%', right: '-35%', height: 2, backgroundColor: colors.borderStrong},
  stepLineOn: {backgroundColor: colors.primary},
  card: {
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: space.lg,
    marginBottom: space.md,
    ...shadow,
  },
  section: {
    fontSize: 11,
    fontWeight: '800',
    color: colors.muted,
    textTransform: 'uppercase',
    letterSpacing: 1.6,
    marginBottom: space.sm,
    marginTop: space.md,
    marginLeft: 2,
  },
  paragraph: {fontSize: 15, color: colors.text, lineHeight: 22, marginBottom: space.sm},
  muted: {color: colors.muted},
  row: {flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 8, gap: space.md},
  rowLabel: {fontSize: 14, color: colors.muted, flexShrink: 0},
  rowValue: {fontSize: 14, color: colors.text, fontWeight: '700', flexShrink: 1, textAlign: 'right'},
  navRow: {flexDirection: 'row', alignItems: 'center', paddingVertical: 14, paddingHorizontal: 2},
  navRowBorder: {borderBottomWidth: 1, borderBottomColor: colors.border},
  navTitle: {fontSize: 16, fontWeight: '700', color: colors.text},
  navSub: {fontSize: 13, color: colors.muted, marginTop: 2},
  chevron: {fontSize: 26, color: colors.faint, marginLeft: space.sm, marginTop: -2},
  button: {
    minHeight: 50,
    borderRadius: radius.md,
    paddingHorizontal: space.lg,
    alignItems: 'center',
    justifyContent: 'center',
    marginVertical: space.xs,
  },
  buttonLarge: {minHeight: 58},
  buttonSecondary: {borderWidth: 1.5, borderColor: colors.primary200},
  buttonShadow: {shadowColor: colors.primary, shadowOpacity: 0.25, shadowRadius: 12, shadowOffset: {width: 0, height: 6}, elevation: 4},
  buttonText: {fontSize: 16, fontWeight: '800'},
  buttonTextLarge: {fontSize: 17, letterSpacing: 0.3},
  link: {alignSelf: 'center', paddingVertical: space.md},
  linkText: {fontSize: 14, fontWeight: '700', color: colors.primary},
  field: {marginBottom: space.lg},
  fieldLabel: {fontSize: 14, fontWeight: '800', color: colors.text, marginBottom: 8},
  input: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    backgroundColor: colors.card,
    paddingHorizontal: space.lg,
    paddingVertical: 14,
    fontSize: 16,
    color: colors.text,
  },
  inputFocused: {borderColor: colors.primaryLight, borderWidth: 1.5},
  inputMultiline: {minHeight: 120, textAlignVertical: 'top'},
  inputError: {borderColor: colors.danger},
  fieldHelp: {fontSize: 12, color: colors.muted, marginTop: 6, lineHeight: 17},
  fieldError: {fontSize: 13, color: colors.danger, marginTop: 6},
  switchRow: {flexDirection: 'row', alignItems: 'center', paddingVertical: space.sm, gap: space.md},
  switchLabel: {fontSize: 15, color: colors.text, fontWeight: '700'},
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    alignSelf: 'flex-start',
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 5,
    gap: 6,
  },
  dot: {width: 8, height: 8, borderRadius: 4},
  pillText: {fontSize: 12, fontWeight: '800'},
  banner: {flexDirection: 'row', borderRadius: radius.md, padding: space.md, marginBottom: space.md, gap: space.md},
  bannerBar: {width: 4, borderRadius: 2},
  bannerTitle: {fontSize: 15, fontWeight: '800'},
  bannerText: {fontSize: 14, color: colors.text, marginTop: 4, lineHeight: 20},
  bannerAction: {marginTop: space.sm, alignSelf: 'flex-start', borderWidth: 1.5, borderRadius: radius.sm, paddingHorizontal: 12, paddingVertical: 6},
  bannerActionText: {fontSize: 13, fontWeight: '800'},
  tile: {
    flexBasis: '47%',
    flexGrow: 1,
    backgroundColor: colors.card,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.border,
    padding: space.lg,
    ...shadow,
  },
  tileValue: {fontSize: 28, fontWeight: '900', color: colors.text},
  tileLabel: {fontSize: 13, color: colors.muted, marginTop: 2, fontWeight: '600'},
  segmented: {flexDirection: 'row', backgroundColor: colors.neutralBg, borderRadius: radius.md, padding: 4, marginBottom: space.md},
  segment: {flex: 1, paddingVertical: 9, alignItems: 'center', borderRadius: radius.sm},
  segmentActive: {backgroundColor: colors.card, ...shadow},
  segmentText: {fontSize: 14, color: colors.muted, fontWeight: '700'},
  segmentTextActive: {color: colors.primary},
  trust: {flexDirection: 'row', alignItems: 'center', gap: 8, justifyContent: 'center', marginTop: space.md},
  trustDot: {width: 6, height: 6, borderRadius: 3, backgroundColor: colors.accent},
  trustText: {fontSize: 12, color: colors.muted},
});
