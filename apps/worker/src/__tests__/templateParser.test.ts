import { describe, it, expect } from 'vitest';
import { resolveTemplates, evaluateCondition } from '../tools/templateParser.js';

// ---------------------------------------------------------------------------
// Shared test context
// ---------------------------------------------------------------------------

const context: Record<string, unknown> = {
	input: {
		project: 'AI',
		name: 'World',
		count: 42,
		flag: true,
		nothing: null,
	},
	step: {
		output: {
			results: [
				{ name: 'first', path_info: '/repos/ai' },
				{ name: 'second', path_info: '/repos/ml' },
			],
			count: 2,
		},
	},
	nested: {
		a: {
			b: {
				c: 'deep value',
			},
		},
	},
	items: ['alpha', 'beta', 'gamma'],
	meta: {
		tags: ['ts', 'vitest'],
		config: { debug: false, retries: 3 },
	},
};

// ---------------------------------------------------------------------------
// resolveTemplates
// ---------------------------------------------------------------------------

describe('resolveTemplates', () => {
	describe('full string expression — type preservation', () => {
		it('returns the raw string value for {{input.project}}', () => {
			const result = resolveTemplates('{{input.project}}', context);
			expect(result).toBe('AI');
			expect(typeof result).toBe('string');
		});

		it('returns the raw number value for {{input.count}}', () => {
			const result = resolveTemplates('{{input.count}}', context);
			expect(result).toBe(42);
			expect(typeof result).toBe('number');
		});

		it('returns the raw boolean value for {{input.flag}}', () => {
			const result = resolveTemplates('{{input.flag}}', context);
			expect(result).toBe(true);
			expect(typeof result).toBe('boolean');
		});

		it('returns the raw object value for {{step.output}}', () => {
			const result = resolveTemplates('{{step.output}}', context);
			expect(result).toEqual(context.step && (context.step as Record<string, unknown>).output);
		});

		it('returns the raw array value for {{items}}', () => {
			const result = resolveTemplates('{{items}}', context);
			expect(result).toEqual(['alpha', 'beta', 'gamma']);
		});
	});

	describe('inline interpolation — always returns string', () => {
		it('interpolates a single expression inside surrounding text', () => {
			const result = resolveTemplates('Hello {{input.name}}!', context);
			expect(result).toBe('Hello World!');
			expect(typeof result).toBe('string');
		});

		it('interpolates multiple expressions in one string', () => {
			// When the string has text between the two expressions the full-match
			// regex does NOT fire and inline interpolation is used instead.
			// Note: "{{A}} / {{B}}" is ambiguous for the full-match regex because
			// `.+?` can backtrack and match across the inner `}}` — it is treated
			// as a single (unresolvable) full expression, returning undefined.
			// Use literal separating text to trigger inline interpolation reliably.
			const result = resolveTemplates('Project: {{input.project}}, Name: {{input.name}}', context);
			expect(result).toBe('Project: AI, Name: World');
		});

		it('converts a number to string when used inline', () => {
			const result = resolveTemplates('Count: {{input.count}}', context);
			expect(result).toBe('Count: 42');
		});
	});

	describe('nested path resolution', () => {
		it('resolves {{step.output.results[0].path_info}}', () => {
			const result = resolveTemplates('{{step.output.results[0].path_info}}', context);
			expect(result).toBe('/repos/ai');
		});

		it('resolves a three-level deep object path {{nested.a.b.c}}', () => {
			const result = resolveTemplates('{{nested.a.b.c}}', context);
			expect(result).toBe('deep value');
		});

		it('resolves {{meta.config.retries}}', () => {
			const result = resolveTemplates('{{meta.config.retries}}', context);
			expect(result).toBe(3);
		});
	});

	describe('array index access', () => {
		it('resolves {{items[0]}}', () => {
			expect(resolveTemplates('{{items[0]}}', context)).toBe('alpha');
		});

		it('resolves {{items[1]}}', () => {
			expect(resolveTemplates('{{items[1]}}', context)).toBe('beta');
		});

		it('resolves {{items[2]}}', () => {
			expect(resolveTemplates('{{items[2]}}', context)).toBe('gamma');
		});

		it('resolves {{step.output.results[1].name}}', () => {
			expect(resolveTemplates('{{step.output.results[1].name}}', context)).toBe('second');
		});

		it('resolves {{meta.tags[0]}}', () => {
			expect(resolveTemplates('{{meta.tags[0]}}', context)).toBe('ts');
		});

		it('returns undefined for out-of-bounds index {{items[99]}}', () => {
			expect(resolveTemplates('{{items[99]}}', context)).toBeUndefined();
		});
	});

	describe('undefined / null handling', () => {
		it('returns undefined for an unknown top-level key', () => {
			expect(resolveTemplates('{{missing}}', context)).toBeUndefined();
		});

		it('returns undefined for a missing nested key', () => {
			expect(resolveTemplates('{{input.nonexistent}}', context)).toBeUndefined();
		});

		it('returns undefined when traversing past null ({{input.nothing.child}})', () => {
			expect(resolveTemplates('{{input.nothing.child}}', context)).toBeUndefined();
		});

		it('returns undefined when array index access on non-array', () => {
			expect(resolveTemplates('{{input.project[0]}}', context)).toBeUndefined();
		});

		it('renders empty string inline when expression resolves to undefined', () => {
			const result = resolveTemplates('prefix-{{missing}}-suffix', context);
			expect(result).toBe('prefix--suffix');
		});

		it('renders empty string inline when expression resolves to null', () => {
			const result = resolveTemplates('value: {{input.nothing}}', context);
			expect(result).toBe('value: ');
		});
	});

	describe('object traversal', () => {
		it('resolves values inside a plain object recursively', () => {
			const template = { project: '{{input.project}}', label: 'Name: {{input.name}}' };
			const result = resolveTemplates(template, context);
			expect(result).toEqual({ project: 'AI', label: 'Name: World' });
		});

		it('preserves keys with no expressions unchanged', () => {
			const template = { static: 'no-template', dynamic: '{{input.project}}' };
			const result = resolveTemplates(template, context);
			expect(result).toEqual({ static: 'no-template', dynamic: 'AI' });
		});

		it('resolves nested object structure recursively', () => {
			const template = { outer: { inner: '{{nested.a.b.c}}' } };
			const result = resolveTemplates(template, context);
			expect(result).toEqual({ outer: { inner: 'deep value' } });
		});
	});

	describe('resolving arrays and objects recursively', () => {
		it('resolves every element of a top-level array', () => {
			const template = ['{{input.project}}', '{{input.name}}', 'static'];
			const result = resolveTemplates(template, context);
			expect(result).toEqual(['AI', 'World', 'static']);
		});

		it('resolves arrays nested inside objects', () => {
			const template = { tags: ['{{input.project}}', '{{input.name}}'] };
			const result = resolveTemplates(template, context);
			expect(result).toEqual({ tags: ['AI', 'World'] });
		});

		it('resolves objects nested inside arrays', () => {
			const template = [{ key: '{{input.project}}' }, { key: '{{input.name}}' }];
			const result = resolveTemplates(template, context);
			expect(result).toEqual([{ key: 'AI' }, { key: 'World' }]);
		});

		it('passes through non-string primitives in arrays unchanged', () => {
			const template = [1, true, null, '{{input.project}}'];
			const result = resolveTemplates(template, context);
			expect(result).toEqual([1, true, null, 'AI']);
		});

		it('passes through non-string, non-object primitive values unchanged', () => {
			expect(resolveTemplates(99, context)).toBe(99);
			expect(resolveTemplates(true, context)).toBe(true);
			expect(resolveTemplates(null, context)).toBe(null);
		});
	});
});

// ---------------------------------------------------------------------------
// evaluateCondition
// ---------------------------------------------------------------------------

describe('evaluateCondition', () => {
	describe('equality operator ==', () => {
		it('"1 == 1" → true', () => {
			expect(evaluateCondition('1 == 1', {})).toBe(true);
		});

		it('"1 == 2" → false', () => {
			expect(evaluateCondition('1 == 2', {})).toBe(false);
		});

		it('"hello == hello" → true', () => {
			expect(evaluateCondition('hello == hello', {})).toBe(true);
		});

		it('"hello == world" → false', () => {
			expect(evaluateCondition('hello == world', {})).toBe(false);
		});
	});

	describe('inequality operator !=', () => {
		it('"hello != world" → true', () => {
			expect(evaluateCondition('hello != world', {})).toBe(true);
		});

		it('"hello != hello" → false', () => {
			expect(evaluateCondition('hello != hello', {})).toBe(false);
		});

		it('"1 != 2" → true', () => {
			expect(evaluateCondition('1 != 2', {})).toBe(true);
		});
	});

	describe('greater-than operator >', () => {
		it('"3 > 1" → true', () => {
			expect(evaluateCondition('3 > 1', {})).toBe(true);
		});

		it('"1 > 3" → false', () => {
			expect(evaluateCondition('1 > 3', {})).toBe(false);
		});

		it('"3 > 3" → false', () => {
			expect(evaluateCondition('3 > 3', {})).toBe(false);
		});
	});

	describe('less-than operator <', () => {
		it('"0 < 5" → true', () => {
			expect(evaluateCondition('0 < 5', {})).toBe(true);
		});

		it('"5 < 0" → false', () => {
			expect(evaluateCondition('5 < 0', {})).toBe(false);
		});

		it('"5 < 5" → false', () => {
			expect(evaluateCondition('5 < 5', {})).toBe(false);
		});
	});

	describe('greater-than-or-equal operator >=', () => {
		it('"5 >= 5" → true', () => {
			expect(evaluateCondition('5 >= 5', {})).toBe(true);
		});

		it('"6 >= 5" → true', () => {
			expect(evaluateCondition('6 >= 5', {})).toBe(true);
		});

		it('"4 >= 5" → false', () => {
			expect(evaluateCondition('4 >= 5', {})).toBe(false);
		});
	});

	describe('less-than-or-equal operator <=', () => {
		it('"5 <= 5" → true', () => {
			expect(evaluateCondition('5 <= 5', {})).toBe(true);
		});

		it('"4 <= 5" → true', () => {
			expect(evaluateCondition('4 <= 5', {})).toBe(true);
		});

		it('"6 <= 5" → false', () => {
			expect(evaluateCondition('6 <= 5', {})).toBe(false);
		});
	});

	describe('truthy/falsy check (no operator)', () => {
		it('non-empty string is truthy', () => {
			expect(evaluateCondition('hello', {})).toBe(true);
		});

		it('"true" is truthy', () => {
			expect(evaluateCondition('true', {})).toBe(true);
		});

		it('"1" is truthy', () => {
			expect(evaluateCondition('1', {})).toBe(true);
		});

		it('"0" is falsy', () => {
			expect(evaluateCondition('0', {})).toBe(false);
		});

		it('"false" is falsy', () => {
			expect(evaluateCondition('false', {})).toBe(false);
		});

		it('empty string is falsy', () => {
			expect(evaluateCondition('', {})).toBe(false);
		});
	});

	describe('with template expressions in condition', () => {
		it('resolves context value before comparison', () => {
			const ctx = { step: { output: { count: 2 } } };
			expect(evaluateCondition('{{step.output.count}} == 2', ctx)).toBe(true);
		});

		it('resolves to false when context value does not match', () => {
			const ctx = { step: { output: { count: 1 } } };
			expect(evaluateCondition('{{step.output.count}} == 2', ctx)).toBe(false);
		});

		it('resolved undefined renders as empty string — falsy check', () => {
			expect(evaluateCondition('{{missing}}', {})).toBe(false);
		});
	});
});
