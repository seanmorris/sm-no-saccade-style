import { RuleTester } from 'eslint';

import rule from '../source/rules/allman-tabs.js';

const ruleTester = new RuleTester({
	languageOptions: {
		ecmaVersion: 2022
		, sourceType: 'module'
	}
});

ruleTester.run('sm-no-saccade-style/allman-tabs', rule, {
	valid: [
		"if(foo)\n{\n\tbar();\n}"
		, "const short\t    = 1;\nconst longerName = 2;"
		, "promise.then(value => {\n\tbar(value);\n});"
		, "const x = function() {\n\tbar();\n};"
		, "const X = class {\n\tmethod() {\n\t\tbar();\n\t}\n};"
		, "const x = {\n\tmethod() {\n\t\tbar();\n\t}\n};"
		, "if(foo) /* keep comment */\n{\n\tbar();\n}"
		, "switch(value)\n{\n\tcase 1:\n\t\tbreak;\n}"
		, "class X\n{\n\tdestroy(entity){}\n}"
		, "class X\n{\n\tconstructor({\n\t\ta\n\t\t, b\n\t}){}\n}"
		, "if(foo){}"
		, "if(foo) bar();"
		, "if(foo)\nbar();"
		, "if(foo)\n\tbar();"
		, "if(motionParent\n\t&& !world.motionGraph.getParent(motionParent)\n\t&& !maps.has(motionParent)\n){\n\tworld.motionGraph.delete(this);\n}"
		, "if(graphs)\nfor(const graph of graphs)\n{\n\tgraph.delete(entity);\n}"
		, "if(graphs)\n\tfor(const graph of graphs)\n\t{\n\t\tgraph.delete(entity);\n\t}"
		, "new Set(this.mapRenderers.keys())\n.difference(visibleMaps)\n.forEach(map => {\n\tthis.mapRenderers.delete(map);\n\tmap.visible = false;\n});"
		, "const loadSlices = this.map.imageLayers.map(\n\t(layerData, index) => this.constructor.loadImage(layerData).then(image => {\n\t\tbar(image);\n\t})\n);"
	]
	, invalid: [
		{
			code: "if(foo) {\n  bar();\n}"
			, output: "if(foo)\n{\n\tbar();\n}"
			, errors: 2
		}
		, {
			code: "if(foo)\n{\n \tbar();\n}"
			, output: "if(foo)\n{\n\tbar();\n}"
			, errors: [
				{ messageId: 'wrongIndentation' }
				, { messageId: 'mixedSpacesAndTabs' }
			]
		}
		, {
			code: "promise.then(value =>\n{\n\tbar(value);\n});"
			, output: "promise.then(value => {\n\tbar(value);\n});"
			, errors: [{ messageId: 'unexpectedInlineAllmanOpen' }]
		}
		, {
			code: "const x = function()\n{\n\tbar();\n};"
			, output: "const x = function() {\n\tbar();\n};"
			, errors: [{ messageId: 'unexpectedInlineAllmanOpen' }]
		}
		, {
			code: "const X = class\n{\n\tmethod() {\n\t\tbar();\n\t}\n};"
			, output: "const X = class {\n\tmethod() {\n\t\tbar();\n\t}\n};"
			, errors: [{ messageId: 'unexpectedInlineAllmanOpen' }]
		}
		, {
			code: "const x = {\n\tmethod()\n\t{\n\t\tbar();\n\t}\n};"
			, output: "const x = {\n\tmethod() {\n\t\tbar();\n\t}\n};"
			, errors: [{ messageId: 'unexpectedInlineAllmanOpen' }]
		}
		, {
			code: "if(foo)  \n{\n\tbar();\n}"
			, output: "if(foo)\n{\n\tbar();\n}"
			, errors: [{ message: 'Unexpected horizontal whitespace before an Allman opening brace.' }]
		}
		, {
			code: "if(foo) /* keep comment */ {\n\tbar();\n}"
			, output: null
			, errors: [{ messageId: 'expectedAllmanOpen' }]
		}
		, {
			code: "class X\n{\n\tdestroy(entity){\n\t\tbar();\n\t}\n}"
			, output: "class X\n{\n\tdestroy(entity)\n{\n\t\tbar();\n\t}\n}"
			, errors: [{ messageId: 'expectedAllmanOpen' }]
		}
		, {
			code: "class X\n{\n\tconstructor({\n\t\ta\n\t\t, b\n\t})\n\t{\n\t\tbar();\n\t}\n}"
			, output: "class X\n{\n\tconstructor({\n\t\ta\n\t\t, b\n\t}){\n\t\tbar();\n\t}\n}"
			, errors: [{ messageId: 'unexpectedMultilineFunctionOpen' }]
		}
		, {
			code: "if(motionParent\n\t&& !world.motionGraph.getParent(motionParent)\n\t&& !maps.has(motionParent)\n)\n{\n\tworld.motionGraph.delete(this);\n}"
			, output: "if(motionParent\n\t&& !world.motionGraph.getParent(motionParent)\n\t&& !maps.has(motionParent)\n){\n\tworld.motionGraph.delete(this);\n}"
			, errors: [{ messageId: 'unexpectedMultilineControlOpen' }]
		}
		, {
			code: "const loadSlices = this.map.imageLayers.map(\n\t(layerData, index) => this.constructor.loadImage(layerData).then(image => {\n\t\tbar(image);\n\t}\n\t));"
			, output: "const loadSlices = this.map.imageLayers.map(\n\t(layerData, index) => this.constructor.loadImage(layerData).then(image => {\n\t\tbar(image);\n\t})\n);"
			, errors: [{ messageId: 'mixedClosingRays' }]
		}
		, {
			code: "const loadSlices = this.map.imageLayers.map(\n\t(layerData, index) => this.constructor.loadImage(layerData).then(image => {\n\t\tbar(image);\n\t}\n));"
			, output: "const loadSlices = this.map.imageLayers.map(\n\t(layerData, index) => this.constructor.loadImage(layerData).then(image => {\n\t\tbar(image);\n\t})\n);"
			, errors: [{ messageId: 'mixedClosingRays' }]
		}
		, {
			code: "switch(value) {\n\tcase 1:\n\t\tbreak;\n}"
			, output: "switch(value)\n{\n\tcase 1:\n\t\tbreak;\n}"
			, errors: [{ messageId: 'expectedAllmanOpen' }]
		}
		, {
			code: "promise.then(value => /* keep comment */\n{\n\tbar(value);\n});"
			, output: null
			, errors: [{ messageId: 'unexpectedInlineAllmanOpen' }]
		}
	]
});
