<?php

/**
 * Copyright (c) 2020 David Grudl (https://davidgrudl.com)
 */

declare(strict_types=1);

namespace DG\ComposerFrontline;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Json\JsonManipulator;
use Composer\Package\Version\VersionParser;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\CompositeRepository;
use Composer\Repository\PlatformRepository;
use Composer\Repository\RepositorySet;
use Composer\Semver\Constraint\Constraint;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class UpdateCommand extends BaseCommand
{
	private const ARGUMENT_SHORTCUTS = [
		'nette' => ['nette/*', 'tracy/*', 'latte/*'],
	];


	protected function configure()
	{
		$this->setName('frontline')
			->setDescription('Upgrades version constraints in composer.json to the latest versions.')
			->setDefinition([
				new InputArgument('packages', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Packages that should be updated, if not provided all packages are. Accepts wildchars like doctrine/*.'),
			]);
	}


	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$jsonFile = new JsonFile(Factory::getComposerFile());
		if (!$jsonFile->exists()) {
			$this->getIO()->writeError('Could not find your composer.json file!');
			return 1;
		}

		$masks = $this->getMasks($input);
		$result = $this->updatePackages($jsonFile->read(), $masks);

		if (!$result) {
			$output->writeln('All version constraints in composer.json are up to date.');
			return 0;
		}

		$this->saveJson($jsonFile, $result);

		$table = new Table($output);
		$table->setRows(array_map(function ($row) { return [$row[1] . '  ', $row[2], ' → ', $row[3]]; }, $result));
		$table->setStyle('compact');
		$table->render();

		return 0;
	}


	private function updatePackages(array $composerDefinition, array $masks): array
	{
		$composer = $this->getComposer();
		$versionSelector = $this->createVersionSelector($composer);
		$versionParser = new VersionParser;
		$res = [];

		foreach (['require', 'require-dev'] as $requireKey) {
			foreach ($composerDefinition[$requireKey] ?? [] as $packageName => $constraintStr) {
				if (
					PlatformRepository::isPlatformPackage($packageName)
					|| !self::matchesMask($masks, $packageName)
					|| substr($constraintStr, 0, 3) === 'dev'
				) {
					continue;
				}

				$constraint = $versionParser->parseConstraints($constraintStr);
				$latestPackage = $versionSelector->findBestCandidate($packageName);

				if (
					!$latestPackage
					|| $constraint->matches(new Constraint('=', $latestPackage->getVersion()))
				) {
					continue;
				}

				$newConstraint = $versionSelector->findRecommendedRequireVersion($latestPackage);
				$res[] = [$requireKey, $packageName, $constraintStr, $newConstraint];
			}
		}

		return $res;
	}


	private function getMasks(InputInterface $input): array
	{
		$masks = [];
		foreach ($input->getArgument('packages') as $arg) {
			if (isset(self::ARGUMENT_SHORTCUTS[$arg])) {
				$masks = array_merge($masks, self::ARGUMENT_SHORTCUTS[$arg]);
				continue;
			}
			if (strpos($arg, '/') === false) {
				$arg .= '/*';
			}
			$masks[] = $arg;
		}
		return $masks ?: ['*'];
	}


	private static function matchesMask(array $masks, string $packageName): bool
	{
		foreach ($masks as $mask) {
			if (fnmatch($mask, $packageName)) {
				return true;
			}
		}
		return false;
	}


	private function createVersionSelector(Composer $composer): VersionSelector
	{
		$repositorySet = new RepositorySet($composer->getPackage()->getMinimumStability(), $composer->getPackage()->getStabilityFlags());
		$repositorySet->addRepository(new CompositeRepository($composer->getRepositoryManager()->getRepositories()));

		$platformOverrides = $composer->getConfig()->get('platform') ?: [];
		$platformRepo = new PlatformRepository([], $platformOverrides);

		return new VersionSelector($repositorySet, $platformRepo);
	}


	private function saveJson(JsonFile $jsonFile, array $result): void
	{
		$json = $jsonFile->read();
		$contents = file_get_contents($jsonFile->getPath());
		$manipulator = new JsonManipulator($contents);

		$ok = true;
		foreach ($result as [$requireKey, $package,, $newConstraint]) {
			$ok = $ok && $manipulator->addLink($requireKey, $package, $newConstraint);
			$json[$requireKey][$package] = $newConstraint;
		}

		if ($ok) {
			file_put_contents($jsonFile->getPath(), $manipulator->getContents());
		} else {
			$jsonFile->write($json);
		}
	}
}
