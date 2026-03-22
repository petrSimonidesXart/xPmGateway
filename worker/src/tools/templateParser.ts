/**
 * Resolves template expressions like {{input.project}}, {{find_project.output.results[0].path_info}}.
 *
 * Context shape:
 * {
 *   input: { project: "AI", task: "Impl" },
 *   find_project: { output: { results: [{ name: "AI", path_info: "..." }], count: 1 } },
 *   loop_item: { name: "subtask1" }  // set by loop runner
 * }
 */

/** Resolve all {{...}} expressions in a value (string, object, or array). */
export function resolveTemplates(value: unknown, context: Record<string, unknown>): unknown {
	if (typeof value === 'string') {
		return resolveString(value, context);
	}
	if (Array.isArray(value)) {
		return value.map((item) => resolveTemplates(item, context));
	}
	if (value !== null && typeof value === 'object') {
		const result: Record<string, unknown> = {};
		for (const [k, v] of Object.entries(value)) {
			result[k] = resolveTemplates(v, context);
		}
		return result;
	}
	return value;
}

/** Resolve a single string. Handles both full-replacement and inline expressions. */
function resolveString(str: string, context: Record<string, unknown>): unknown {
	// If the entire string is a single expression, return the raw value (preserves type)
	const fullMatch = /^\{\{([^}]+)\}\}$/.exec(str.trim());
	if (fullMatch) {
		return resolvePath(fullMatch[1].trim(), context);
	}

	// Otherwise, interpolate inline expressions as strings
	return str.replace(/\{\{(.+?)\}\}/g, (_match, expr: string) => {
		const val = resolvePath(expr.trim(), context);
		return val === undefined || val === null ? '' : String(val);
	});
}

/** Resolve a dot-path expression like "find_project.output.results[0].path_info". */
function resolvePath(path: string, context: Record<string, unknown>): unknown {
	// Tokenize: split by dots and bracket access
	const tokens = tokenize(path);
	let current: unknown = context;

	for (const token of tokens) {
		if (current === null || current === undefined) return undefined;

		if (typeof token === 'number') {
			if (!Array.isArray(current)) return undefined;
			current = current[token];
		} else {
			if (typeof current !== 'object') return undefined;
			current = (current as Record<string, unknown>)[token];
		}
	}

	return current;
}

/** Tokenize a path expression into string keys and numeric indices. */
function tokenize(path: string): Array<string | number> {
	const tokens: Array<string | number> = [];
	const regex = /([^.\[\]]+)|\[(\d+)\]/g;
	let match: RegExpExecArray | null;

	while ((match = regex.exec(path)) !== null) {
		if (match[1] !== undefined) {
			tokens.push(match[1]);
		} else if (match[2] !== undefined) {
			tokens.push(parseInt(match[2], 10));
		}
	}

	return tokens;
}

/** Evaluate a simple condition expression like "{{step.output.count}} == 1". */
export function evaluateCondition(expr: string, context: Record<string, unknown>): boolean {
	// Resolve any template expressions in the condition
	const resolved = resolveString(expr, context);
	const resolvedStr = String(resolved);

	// Support: ==, !=, >, <, >=, <=
	const opMatch = /^(.+?)\s*(==|!=|>=|<=|>|<)\s*(.+?)$/.exec(resolvedStr);
	if (!opMatch) {
		// Truthy check
		return !!resolved && resolved !== '0' && resolved !== 'false' && resolved !== '';
	}

	const [, leftStr, op, rightStr] = opMatch;
	const left = parseValue(leftStr.trim());
	const right = parseValue(rightStr.trim());

	switch (op) {
		case '==': return left == right; // eslint-disable-line eqeqeq
		case '!=': return left != right; // eslint-disable-line eqeqeq
		case '>': return Number(left) > Number(right);
		case '<': return Number(left) < Number(right);
		case '>=': return Number(left) >= Number(right);
		case '<=': return Number(left) <= Number(right);
		default: return false;
	}
}

function parseValue(str: string): string | number | boolean {
	if (str === 'true') return true;
	if (str === 'false') return false;
	if (str === 'null' || str === 'undefined') return '';
	const num = Number(str);
	if (!isNaN(num) && str !== '') return num;
	// Strip quotes if present
	if ((str.startsWith('"') && str.endsWith('"')) || (str.startsWith("'") && str.endsWith("'"))) {
		return str.slice(1, -1);
	}
	return str;
}
