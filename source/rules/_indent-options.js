const CONTROL_BODY_PROPERTIES = new Map([
	['DoWhileStatement', ['body']]
	, ['ForInStatement', ['body']]
	, ['ForOfStatement', ['body']]
	, ['ForStatement', ['body']]
	, ['IfStatement', ['consequent', 'alternate']]
	, ['WhileStatement', ['body']]
	, ['WithStatement', ['body']]
]);

export const ignoredSingleStatementNodes = [...CONTROL_BODY_PROPERTIES.entries()]
	.flatMap(([parentType, properties]) => properties.map(
		(property) => `${parentType} > :not(BlockStatement).${property}`
	));

export const indentOptions = [
	'tab'
	, {
		ignoredNodes: ignoredSingleStatementNodes
	}
];
