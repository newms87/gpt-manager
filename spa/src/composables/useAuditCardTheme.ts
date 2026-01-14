import { computed, inject, provide, ref, type ComputedRef, type InjectionKey, type Ref } from "vue";

export type AuditCardTheme = "dark" | "light";

const AUDIT_CARD_THEME_KEY: InjectionKey<Ref<AuditCardTheme>> = Symbol("auditCardTheme");

/**
 * Provides the audit card theme to child components.
 * Call this in a parent component to set the theme for all nested audit card components.
 */
export function provideAuditCardTheme(theme?: Ref<AuditCardTheme>) {
	const themeRef = theme ?? ref<AuditCardTheme>("dark");
	provide(AUDIT_CARD_THEME_KEY, themeRef);
	return themeRef;
}

/**
 * Uses the audit card theme from a parent provider.
 * Defaults to "dark" if no provider is found.
 */
export function useAuditCardTheme(): {
	theme: Ref<AuditCardTheme>;
	isDark: ComputedRef<boolean>;
	themeClass: (darkClass: string, lightClass: string) => string;
} {
	const theme = inject(AUDIT_CARD_THEME_KEY, ref<AuditCardTheme>("dark"));

	const isDark = computed(() => theme.value === "dark");

	const themeClass = (darkClass: string, lightClass: string): string => {
		return isDark.value ? darkClass : lightClass;
	};

	return {
		theme,
		isDark,
		themeClass
	};
}
