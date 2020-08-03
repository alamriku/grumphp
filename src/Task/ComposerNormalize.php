<?php

namespace GrumPHP\Task;

use GrumPHP\Fixer\Provider\FixableProcessProvider;
use GrumPHP\Runner\FixableTaskResult;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ComposerNormalize extends AbstractExternalTask
{
  /**
   * @var \GrumPHP\Formatter\ComposerNormalizeFormatter
   */
    protected $formatter;

    public function canRunInContext(ContextInterface $context): bool
    {
        return $context instanceof GitPreCommitContext || $context instanceof RunContext;
    }

    public static function getConfigurableOptions(): OptionsResolver
    {
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'indent_size' => null,
            'indent_style' => null,
            'no_update_lock' => true,
            'use_standalone' => false,
            'verbose' => false,
        ]);

        $resolver->addAllowedTypes('indent_size', ['int', 'null']);
        $resolver->addAllowedTypes('indent_style', ['string', 'null']);
        $resolver->addAllowedValues('indent_style', ['tab', 'space', null]);
        $resolver->addAllowedTypes('no_update_lock', ['bool']);
        $resolver->addAllowedTypes('verbose', ['bool']);

        return $resolver;
    }

    public function run(ContextInterface $context): TaskResultInterface
    {
        $config = $this->getConfig()->getOptions();
        $files = $context->getFiles()
            ->path(pathinfo('composer.json', PATHINFO_DIRNAME))
            ->name(pathinfo('composer.json', PATHINFO_BASENAME));

        if (0 === count($files)) {
            return TaskResult::createSkipped($this, $context);
        }

        $executable = $config['use_standalone'] ? 'composer-normalize' : 'composer';
        $arguments = $this->processBuilder->createArgumentsForCommand($executable);
        $arguments->addOptionalArgument('normalize', !$config['use_standalone']);
        $arguments->add('--dry-run');

        if ($config['indent_size'] !== null && $config['indent_style'] !== null) {
            $arguments->addOptionalArgument('--indent-style=%s', $config['indent_style']);
            $arguments->addOptionalArgument('--indent-size=%s', $config['indent_size']);
        }

        $arguments->addOptionalArgument('--no-update-lock', $config['no_update_lock']);
        $arguments->addOptionalArgument('-q', $config['verbose']);

        $process = $this->processBuilder->buildProcess($arguments);
        $process->run();

        if (!$process->isSuccessful()) {
            $output = $this->formatter->format($process);
            $arguments->removeElement('--dry-run');
            $process = $this->processBuilder->buildProcess($arguments);
            $fixerCommand = $process->getCommandLine();
            $output .= $this->formatter->formatErrorMessage($fixerCommand);
            return new FixableTaskResult(
                TaskResult::createFailed($this, $context, $output),
                FixableProcessProvider::provide($fixerCommand)
            );
        }

        return TaskResult::createPassed($this, $context);
    }
}
