/**
 * CallbackConfigField — renders the .ecfg config block below the type chips.
 *
 * Dynamically swaps content based on `callbackType`:
 *   noop           → info notice (no user input)
 *   filter_hook    → hook_name text input
 *   wp_remote_post → url + method select + timeout number (inline)
 *   php_code       → dark monospace textarea with SEC-010-03 execution warning
 *
 * @since 0.2.0
 */
import { __ } from '@wordpress/i18n';

const DEFAULT_TIMEOUT = 30;

// ---------------------------------------------------------------------------
// Sub-forms
// ---------------------------------------------------------------------------

function NoopConfig() {
	return (
		<p className="description">
			{__(
				'No code runs. Useful for declarative or schema-only abilities that describe capabilities without requiring server-side execution.',
				'acrossai-abilities-manager'
			)}
		</p>
	);
}

function FilterHookConfig({ config, onChange }) {
	return (
		<div>
			<label htmlFor="cb-hook-name">
				<strong>
					{__('Filter hook name', 'acrossai-abilities-manager')}
				</strong>
			</label>
			<br />
			<input
				id="cb-hook-name"
				type="text"
				className="large-text code"
				value={config.hook_name || ''}
				placeholder="my_custom_hook"
				onChange={(e) =>
					onChange({ ...config, hook_name: e.target.value })
				}
			/>
			<p className="description">
				{__(
					'WordPress filter hook to invoke. Receives the ability input as its second argument.',
					'acrossai-abilities-manager'
				)}{' '}
				<code>
					{
						"apply_filters( 'acrossai_ability_execute_{hook_name}', [], $input )"
					}
				</code>
			</p>
		</div>
	);
}

function RemotePostConfig({ config, onChange }) {
	return (
		<div>
			<div style={{ marginBottom: '8px' }}>
				<label htmlFor="cb-url">
					<strong>{__('URL', 'acrossai-abilities-manager')}</strong>
				</label>
				<br />
				<input
					id="cb-url"
					type="url"
					className="large-text"
					value={config.url || ''}
					placeholder="https://example.com/webhook"
					onChange={(e) =>
						onChange({ ...config, url: e.target.value })
					}
				/>
			</div>

			<div
				style={{ display: 'flex', gap: '12px', alignItems: 'flex-end' }}
			>
				<div>
					<label htmlFor="cb-method">
						<strong>
							{__('Method', 'acrossai-abilities-manager')}
						</strong>
					</label>
					<br />
					<select
						id="cb-method"
						style={{ width: '90px' }}
						value={config.method || 'POST'}
						onChange={(e) =>
							onChange({ ...config, method: e.target.value })
						}
					>
						<option value="POST">POST</option>
						<option value="GET">GET</option>
						<option value="PUT">PUT</option>
						<option value="PATCH">PATCH</option>
					</select>
				</div>

				<div>
					<label htmlFor="cb-timeout">
						<strong>
							{__('Timeout (s)', 'acrossai-abilities-manager')}
						</strong>
					</label>
					<br />
					<input
						id="cb-timeout"
						type="number"
						style={{ width: '80px' }}
						min={1}
						max={30}
						value={config.timeout || DEFAULT_TIMEOUT}
						onChange={(e) =>
							onChange({
								...config,
								timeout:
									parseInt(e.target.value, 10) ||
									DEFAULT_TIMEOUT,
							})
						}
					/>
				</div>
			</div>

			<p className="description" style={{ marginTop: '6px' }}>
				{__(
					'The ability input is sent as a JSON body. The response body is decoded and returned as the ability result.',
					'acrossai-abilities-manager'
				)}
			</p>
		</div>
	);
}

function PhpCodeConfig({ config, onChange }) {
	return (
		<div>
			{/* SEC-010-03: execution warning label above textarea */}
			<div className="acrossai-php-warning">
				<strong>
					{__(
						'⚠ php_code — code execution',
						'acrossai-abilities-manager'
					)}
				</strong>
				{' — '}
				{__(
					'This code runs on the server with full WordPress access. Only admins can create or edit php_code abilities.',
					'acrossai-abilities-manager'
				)}
			</div>

			<label htmlFor="cb-php-code">
				{__(
					'PHP code (no opening tag). Variable',
					'acrossai-abilities-manager'
				)}{' '}
				<code>$input</code>{' '}
				{__(
					'contains the ability input.',
					'acrossai-abilities-manager'
				)}
			</label>
			<br />
			<textarea
				id="cb-php-code"
				className="dark-code code-lt"
				value={config.code || ''}
				placeholder="return strtoupper( $input );"
				onChange={(e) => onChange({ ...config, code: e.target.value })}
			/>
		</div>
	);
}

// ---------------------------------------------------------------------------
// Main component
// ---------------------------------------------------------------------------

/**
 * CallbackConfigField component.
 *
 * @param {Object}   props
 * @param {string}   props.callbackType One of: noop | filter_hook | wp_remote_post | php_code
 * @param {Object}   props.config       Current callback_config object (parsed from JSON).
 * @param {Function} props.onChange     Called with new config object on any change.
 * @return {JSX.Element}
 */
export default function CallbackConfigField({
	callbackType,
	config,
	onChange,
}) {
	const cfg = config || {};

	const LABEL_MAP = {
		noop: __('noop — no execution', 'acrossai-abilities-manager'),
		filter_hook: __(
			'filter_hook — WordPress filter',
			'acrossai-abilities-manager'
		),
		wp_remote_post: __(
			'wp_remote_post — HTTP request',
			'acrossai-abilities-manager'
		),
		php_code: __('php_code — code execution', 'acrossai-abilities-manager'),
	};

	return (
		<div className="ecfg">
			<span className="ecfg-lbl">
				{LABEL_MAP[callbackType] || callbackType}
			</span>

			{'noop' === callbackType && <NoopConfig />}
			{'filter_hook' === callbackType && (
				<FilterHookConfig config={cfg} onChange={onChange} />
			)}
			{'wp_remote_post' === callbackType && (
				<RemotePostConfig config={cfg} onChange={onChange} />
			)}
			{'php_code' === callbackType && (
				<PhpCodeConfig config={cfg} onChange={onChange} />
			)}
		</div>
	);
}
