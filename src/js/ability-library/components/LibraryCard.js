import {
	Button,
	CheckboxControl,
	RadioControl,
	ToggleControl,
} from '@wordpress/components';
import { Fragment, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { chevronDown, chevronUp } from '@wordpress/icons';

/**
 * Group slugs by sub_group while preserving registration order — Feature 033.
 *
 * Walks the slug list once, bucketing by the (possibly empty) subGroup key.
 * The empty-string bucket appears first when present, with no heading. Later
 * buckets follow in first-seen order. Named export so it can be unit-tested
 * without rendering React (per PATTERN-NAMED-EXPORT-JEST).
 *
 * @param {Array<{slug:string, slugLabel:string, name:string, subGroup:string, subGroupLabel:string}>} slugs
 * @return {Array<{subGroup:string, subGroupLabel:string, items:Array}>}
 */
export function groupBySubGroupPreservingOrder(slugs) {
	const order = [];
	const groups = new Map();
	for (const slug of slugs) {
		const key = slug.subGroup || '';
		if (!groups.has(key)) {
			groups.set(key, {
				subGroup: key,
				subGroupLabel: slug.subGroupLabel || '',
				items: [],
			});
			order.push(key);
		}
		groups.get(key).items.push(slug);
	}
	return order.map((key) => groups.get(key));
}

/**
 * Card renderer for a single category ability group.
 *
 * Header row: toggle + label on left, All/Specific radio on right.
 * Slug checkboxes appear below when mode is "specific". When add-on
 * abilities declare an optional sub_group, the matching checkboxes are
 * rendered under a small <h4> heading; display-only (saved config is
 * unaffected).
 *
 * @param {Object}   props
 * @param {Object}   props.item     { category, categoryLabel, slugs: [{slug, slugLabel, name, subGroup, subGroupLabel}] }
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

	// Per-card expand/collapse — local component state, default expanded
	// (matches the "see what's inside without flipping the radio" UX added
	// in turn 2). No persistence; resets per page load.
	const [expanded, setExpanded] = useState(true);
	const canExpand = enabled && slugs.length > 0;

	function update(patch) {
		onChange(category, { ...entry, ...patch });
	}

	return (
		<div className="acrossai-library-card">
			<div className="acrossai-library-card__header">
				{canExpand && (
					<Button
						className="acrossai-library-card__disclosure"
						icon={expanded ? chevronUp : chevronDown}
						label={
							expanded
								? __(
										'Collapse ability list',
										'acrossai-abilities-manager'
								  )
								: __(
										'Expand ability list',
										'acrossai-abilities-manager'
								  )
						}
						showTooltip
						onClick={() => setExpanded((prev) => !prev)}
						aria-expanded={expanded}
					/>
				)}

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

			{/* Slug list — Feature 033 contract: rendered when (a) enabled, (b) at
			    least one slug, AND (c) the per-card disclosure is expanded. Under
			    mode === 'specific' the rows are interactive checkboxes; under
			    mode === 'all' they're read-only label rows. */}
			{enabled && slugs.length > 0 && expanded && (
				<div
					className={
						'acrossai-library-card__slugs' +
						(mode === 'all'
							? ' acrossai-library-card__slugs--readonly'
							: '')
					}
				>
					{groupBySubGroupPreservingOrder(slugs).map(
						({ subGroup, subGroupLabel, items }) => (
							<Fragment key={subGroup || '__ungrouped'}>
								{subGroup !== '' && (
									<h4 className="acrossai-library-card__subgroup-heading">
										{subGroupLabel}
									</h4>
								)}
								{items.map(({ slug, slugLabel, name }) =>
									mode === 'specific' ? (
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
									) : (
										<div
											key={slug}
											className="acrossai-library-card__slug-readonly"
										>
											{slugLabel || name}
										</div>
									)
								)}
							</Fragment>
						)
					)}
				</div>
			)}
		</div>
	);
}
