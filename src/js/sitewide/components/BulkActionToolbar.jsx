/**
 * Bulk Action Toolbar — appears when one or more abilities are selected.
 *
 * @since 0.1.0
 */
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { Button, SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE_NAME } from '../store/index';

const BULK_ACTIONS = [
	{ value: '', label: __('— Select Action —', 'acrossai-abilities-manager') },
	{ value: 'allow', label: __('Allow', 'acrossai-abilities-manager') },
	{ value: 'disallow', label: __('Disallow', 'acrossai-abilities-manager') },
	{
		value: 'reset',
		label: __('Reset Overrides', 'acrossai-abilities-manager'),
	},
];

/**
 * BulkActionToolbar component.
 *
 * @param {Object}   props
 * @param {string[]} props.selectedSlugs Currently selected slugs.
 * @param {Function} props.onComplete    Called after a bulk action completes.
 * @return {JSX.Element}
 */
export default function BulkActionToolbar({ selectedSlugs, onComplete }) {
	const dispatch = useDispatch(STORE_NAME);
	const [action, setAction] = useState('');
	const [isRunning, setRunning] = useState(false);

	if (!selectedSlugs.length) {
		return <div className="acrossai-bulk-toolbar" />;
	}

	async function handleApply() {
		if (!action) {
			return;
		}
		setRunning(true);
		try {
			await dispatch.bulkAction(selectedSlugs, action);
		} finally {
			setRunning(false);
			setAction('');
			onComplete();
		}
	}

	return (
		<div className="acrossai-bulk-toolbar">
			<span className="acrossai-bulk-toolbar__count">
				{selectedSlugs.length === 1
					? __('1 ability selected', 'acrossai-abilities-manager')
					: sprintf(
							/* translators: %d: number of abilities */
							__(
								'%d abilities selected',
								'acrossai-abilities-manager'
							),
							selectedSlugs.length
						)}
			</span>

			<SelectControl
				value={action}
				options={BULK_ACTIONS}
				onChange={setAction}
				hideLabelFromVision
				label={__('Bulk action', 'acrossai-abilities-manager')}
			/>

			<Button
				variant="secondary"
				onClick={handleApply}
				disabled={!action || isRunning}
				isBusy={isRunning}
			>
				{__('Apply', 'acrossai-abilities-manager')}
			</Button>

			{isRunning && <Spinner />}
		</div>
	);
}
