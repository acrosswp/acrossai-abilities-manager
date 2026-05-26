/**
 * TriStateBadgeCell — renders a tri-state (true/false/null) value as a
 * coloured badge, with a "(Default)" suffix when the value comes from
 * the registry rather than a stored override.
 *
 * @since 0.1.0
 */
import { __ } from '@wordpress/i18n';

/**
 * @param {Object}       props
 * @param {boolean|null} props.value         Effective value (true/false/null).
 * @param {boolean}      props.hasOverride   Whether a DB override exists.
 * @param {boolean|null} props.registryValue Registry default for this field.
 * @return {JSX.Element}
 */
export default function TriStateBadgeCell({
	value,
	hasOverride,
	registryValue,
}) {
	if (null === value || undefined === value) {
		return (
			<span className="acrossai-tri-badge acrossai-tri-badge--null">
				{'—'}
			</span>
		);
	}

	// A field originates from the registry when there is no override, or when
	// the stored override value equals the registry default.
	const isDefault = !hasOverride || value === registryValue;

	const className = value
		? 'acrossai-tri-badge acrossai-tri-badge--yes'
		: 'acrossai-tri-badge acrossai-tri-badge--no';

	const label = value
		? __('Yes', 'acrossai-abilities-manager')
		: __('No', 'acrossai-abilities-manager');

	return (
		<span className={className}>
			{label}
			{isDefault && (
				<em className="acrossai-tri-badge__default">
					{' ' + __('(Default)', 'acrossai-abilities-manager')}
				</em>
			)}
		</span>
	);
}
