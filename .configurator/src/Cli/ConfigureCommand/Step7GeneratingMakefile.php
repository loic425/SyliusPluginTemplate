<?php

declare(strict_types=1);

namespace Sylius\PluginTemplate\Configurator\Cli\ConfigureCommand;

use Sylius\PluginTemplate\Configurator\Model\PluginConfiguration;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

final class Step7GeneratingMakefile
{
    public function __construct (
        private string $pluginTemplateDir,
    ) {
    }

    public function __invoke(SymfonyStyle $io, PluginConfiguration $configuration, int $stepsTotal): void
    {
        $filesystem = new Filesystem();

        $io->section(sprintf('Step 7 of %d: Generating Makefile', $stepsTotal));

        $filesystem->dumpFile($this->pluginTemplateDir . '/Makefile', $this->getMakefileContent($configuration));

        $io->info('Generated Makefile');

        $io->success(sprintf('Step 7 of %d completed!', $stepsTotal));
    }

    private function getMakefileContent(PluginConfiguration $configuration): string
    {
        $content = <<<MAKEFILE
        serve:
        	@symfony serve --dir=tests/Application --daemon
        server.start: serve
        server.stop:
        	@symfony server:stop --dir=tests/Application
        frontend.install:
        	@cd tests/Application && npm install
        frontend.build:
        	@cd tests/Application && npm run build
        frontend.setup: frontend.install frontend.build
        setup:
        	@composer update
        	@make frontend.setup
        	@cd tests/Application && bin/console doctrine:database:create --if-not-exists
        	@cd tests/Application && bin/console doctrine:migrations:migrate -n
        	@cd tests/Application && bin/console sylius:fixtures:load -n
        	@cd tests/Application && APP_ENV=test bin/console doctrine:database:create --if-not-exists
        	@cd tests/Application && APP_ENV=test bin/console doctrine:migrations:migrate -n
        	@cd tests/Application && APP_ENV=test bin/console sylius:fixtures:load -n

        MAKEFILE;

        if ($configuration->useEcs()) {
            $content .= <<<MAKEFILE
            ecs:
            	@vendor/bin/ecs
            MAKEFILE;
        }

        if ($configuration->usePsalm()) {
            $content .= <<<MAKEFILE
            psalm:
            	@vendor/bin/psalm
            MAKEFILE;
        }

        if ($configuration->usePhpStan()) {
            $content .= <<<MAKEFILE
            phpstan:
            	@vendor/bin/phpstan
            MAKEFILE;
        }

        if ($configuration->usePhpSpec()) {
            $content .= <<<MAKEFILE
            phpspec:
            	@vendor/bin/phpspec run
            MAKEFILE;
        }

        if ($configuration->usePhpUnit()) {
            $content .= <<<MAKEFILE
            phpunit:
            	@vendor/bin/phpunit
            MAKEFILE;
        }

        if ($configuration->useBehat()) {
            $content .= <<<MAKEFILE
            behat:
            	@vendor/bin/behat
            behat.nojs:
            	@vendor/bin/behat --tags="~@javascript"
            MAKEFILE;
        }

        $staticAnalysisTools = [];

        if ($configuration->useEcs()) {
            $staticAnalysisTools[] = 'ecs';
        }

        if ($configuration->usePsalm()) {
            $staticAnalysisTools[] = 'psalm';
        }

        if ($configuration->usePhpStan()) {
            $staticAnalysisTools[] = 'phpstan';
        }

        if ($staticAnalysisTools !== []) {
            $staticAnalysisTools = implode(' ', $staticAnalysisTools);
            $content .= <<<MAKEFILE
            qa.static-analysis: ${staticAnalysisTools}
            MAKEFILE;
        }

        $testingTools = [];

        if ($configuration->usePhpSpec()) {
            $testingTools[] = 'phpspec';
        }

        if ($configuration->usePhpUnit()) {
            $testingTools[] = 'phpunit';
        }

        if ($testingTools !== []) {
            $testingTools = implode(' ', $testingTools);
            $content .= <<<MAKEFILE
            qa.tests: ${testingTools}
            MAKEFILE;
        }

        if ($staticAnalysisTools !== [] && $testingTools !== []) {
            $content .= <<<MAKEFILE
            ci: static-analysis test

            MAKEFILE;
        }

        return $content;
    }
}
