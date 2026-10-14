import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import * as fs from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';

const root = fileURLToPath(new URL('../', import.meta.url));
const execute = promisify(execFile);
const temporary = await fs.mkdtemp(path.join(tmpdir(), 'no-saccade-packages-'));
const output = path.join(root, 'build/npm-package');
const workspace = JSON.parse(await fs.readFile(path.join(root, 'packages/js/package.json')));
const lock = JSON.parse(await fs.readFile(path.join(root, 'package-lock.json')));

async function run(command, args, cwd = root)
{
	const result = await execute(command, args, {cwd, maxBuffer: 16 * 1024 * 1024});

	return result.stdout;
}

async function json(file, value)
{
	await fs.writeFile(file, JSON.stringify(value, null, 2) + '\n');
}

async function verifyNpm(archive, label)
{
	const consumer = path.join(temporary, label);
	await fs.mkdir(consumer);

	await json(path.join(consumer, 'package.json'), {
		name: 'package-smoke-consumer'
		, private: true
		, type: 'module'
		, dependencies: {
			'sm-no-saccade-style': 'file:' + archive
			, eslint: lock.packages['node_modules/eslint'].version
		}
	});

	await run('npm', ['install', '--ignore-scripts', '--no-audit', '--no-fund'], consumer);

	await fs.copyFile(
		path.join(root, 'scripts/package-smoke.mjs')
		, path.join(consumer, 'smoke.mjs')
	);

	await run(process.execPath, ['smoke.mjs'], consumer);
	console.log(`Verified ${label}: public exports, JS/TS parsing, and stable autofixes.`);
}

try
{
	await run('composer', ['validate', '--strict']);
	await fs.rm(output, {recursive: true, force: true});
	await fs.mkdir(output, {recursive: true});

	const [published] = JSON.parse(await run('npm', [
		'pack', './packages/js', '--pack-destination', output, '--json'
	]));

	assert.equal(published.version, workspace.version);
	await verifyNpm(path.join(output, published.filename), 'npm-package');

	const [git] = JSON.parse(await run('npm', [
		'pack', '--pack-destination', temporary, '--json'
	]));

	await verifyNpm(path.join(temporary, git.filename), 'git-install-package');

	await run('composer', [
		'archive'
		, '--format=zip'
		, '--dir=' + temporary
		, '--file=php-package'
		, '--no-interaction'
	]);

	const extracted = path.join(temporary, 'php-package');
	await fs.mkdir(extracted);

	await run('php', [
		'-r', '(new PharData($argv[1]))->extractTo($argv[2]);'
		, path.join(temporary, 'php-package.zip'), extracted
	]);

	await run('composer', ['validate', '--strict'], extracted);
	const consumer = path.join(temporary, 'composer-consumer');
	await fs.mkdir(consumer);

	await json(path.join(consumer, 'composer.json'), {
		name: 'smoke/package-consumer'
		, require: {'seanmorris/sm-no-saccade-style': workspace.version}
		, repositories: [{
			type: 'path'
			, url: extracted
			, options: {
				symlink: false
				, versions: {'seanmorris/sm-no-saccade-style': workspace.version}
			}
		}]
	});

	await run('composer', ['install', '--no-dev', '--no-interaction', '--prefer-dist'], consumer);

	await fs.copyFile(
		path.join(root, 'scripts/package-smoke.php')
		, path.join(consumer, 'smoke.php')
	);

	await run('php', ['smoke.php'], consumer);
	await run('php', ['vendor/bin/sm-no-saccade-style', 'payload.php'], consumer);
	await fs.writeFile(path.join(consumer, 'long.php'), '<?php\n$' + 'name'.repeat(40) + ' = 1;\n');

	await assert.rejects(
		run('php', ['vendor/bin/sm-no-saccade-style', 'long.php'], consumer)
		, error => error.code === 1 && error.stdout.includes('[max-line-length]')
	);

	console.log('Verified Composer archive: public API, opaque payloads, and CLI exit codes.');
	console.log(`Release artifact: ${path.join(output, published.filename)}`);
}
finally
{
	await fs.rm(temporary, {recursive: true, force: true});
}
