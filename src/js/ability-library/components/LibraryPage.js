import { useEffect, useRef, useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { fetchConfig, saveConfig } from '../api';
import LibraryCard from './LibraryCard';

/**
 * Group flat definitions array by category into card items.
 *
 * @param {Array} definitions Raw definitions from window.acrossaiAbilityLibraryData.
 * @return {Array} Grouped items, one per category.
 */
function groupDefinitions(definitions) {
	const map = new Map();
	for (const def of definitions) {
		const {
			category,
			category_label: categoryLabel,
			slug,
			slug_label: slugLabel,
			name,
		} = def;
		if (!map.has(category)) {
			map.set(category, {
				id: category,
				category,
				categoryLabel,
				slugs: [],
			});
		}
		const group = map.get(category);
		if (!group.slugs.some((s) => s.slug === slug)) {
			group.slugs.push({ slug, slugLabel, name });
		}
	}
	return Array.from(map.values());
}

/**
 * Library admin page — renders one LibraryCard per registered ability group.
 */
export default function LibraryPage() {
	const data = window.acrossaiAbilityLibraryData || {};
	const items = groupDefinitions(data.definitions || []);

	const [config, setConfig] = useState({});
	const [error, setError] = useState(null);

	const initialLoadComplete = useRef(false);

	useEffect(() => {
		fetchConfig()
			.then((saved) => {
				setConfig(saved);
				initialLoadComplete.current = true;
			})
			.catch(() => {
				setError(
					__(
						'Failed to load configuration.',
						'acrossai-abilities-manager'
					)
				);
				initialLoadComplete.current = true;
			});
	}, []);

	function handleChange(category, updatedEntry) {
		const next = { ...config, [category]: updatedEntry };
		setConfig(next);

		if (!initialLoadComplete.current) {
			return;
		}

		setError(null);
		saveConfig(next).catch(() =>
			setError(
				__(
					'Failed to save configuration.',
					'acrossai-abilities-manager'
				)
			)
		);
	}

	return (
		<div className="acrossai-library-page">
			{error && (
				<Notice
					status="error"
					isDismissible
					onRemove={() => setError(null)}
				>
					{error}
				</Notice>
			)}

			{items.length === 0 && (
				<p className="acrossai-library-page__empty">
					{__(
						'No abilities registered yet. Activate an add-on that provides abilities.',
						'acrossai-abilities-manager'
					)}
				</p>
			)}

			{items.map((item) => (
				<LibraryCard
					key={item.category}
					item={item}
					config={config}
					onChange={handleChange}
				/>
			))}
		</div>
	);
}
