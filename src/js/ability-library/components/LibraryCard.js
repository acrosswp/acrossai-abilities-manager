import {
	CheckboxControl,
	RadioControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Card renderer for a single category ability group.
 *
 * Header row: toggle + label on left, All/Specific radio on right.
 * Slug checkboxes appear below when mode is "specific".
 *
 * @param {Object}   props
 * @param {Object}   props.item     { category, categoryLabel, slugs: [{slug, slugLabel, name}] }
 * @param {Object}   props.config   Full config keyed by category.
 * @param {Function} props.onChange Called with (category, updatedEntry) on any change.
 */
export default function LibraryCard({ item, config, onChange }) {
	const { category, categoryLabel, slugs } = item;
	const entry = config[category] ?? {
		enabled: true,
		mode: 'all',
		sub_keys: {},
	};

	const enabled = entry.enabled ?? true;
	const mode = entry.mode ?? 'all';
	const slugsConfig = entry.sub_keys ?? {};

	function update(patch) {
		onChange(category, { ...entry, ...patch });
	}

	return (
		<div className="acrossai-library-card">
			<div className="acrossai-library-card__header">
				<ToggleControl
					__nextHasNoMarginBottom
					label={<strong>{categoryLabel}</strong>}
					checked={enabled}
					onChange={(value) => update({ enabled: value })}
				/>

				{enabled && (
					<RadioControl
						className="acrossai-library-card__mode"
						selected={mode}
						options={[
							{
								label: __('All', 'acrossai-abilities-manager'),
								value: 'all',
							},
							{
								label: __(
									'Specific',
									'acrossai-abilities-manager'
								),
								value: 'specific',
							},
						]}
						onChange={(value) =>
							update({
								mode: value,
								sub_keys: value === 'all' ? {} : slugsConfig,
							})
						}
					/>
				)}
			</div>

			{enabled && mode === 'specific' && slugs.length > 0 && (
				<div className="acrossai-library-card__slugs">
					{slugs.map(({ slug, slugLabel, name }) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={slug}
							label={slugLabel || name}
							checked={slugsConfig[slug] ?? false}
							onChange={(value) =>
								update({
									sub_keys: {
										...slugsConfig,
										[slug]: value,
									},
								})
							}
						/>
					))}
				</div>
			)}
		</div>
	);
}
