<?php
/**
 * This file is part of PHP-Yacc package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
declare(strict_types=1);

namespace PhpYacc\Console;

use PhpYacc\Generator;
use PhpYacc\Grammar\Context;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class GenerateCommand.
 */
class GenerateCommand extends Command
{
    /**
     * @return void
     */
    public function configure(): void
    {
        $this->setName('generate');
        $this->setDescription('The command to generate a parser.');
        $this->addArgument('grammar', InputArgument::REQUIRED, 'Grammar file.');

        $this->addOption('skeleton', 's', InputOption::VALUE_REQUIRED, 'Specify the skeleton to use.');
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Leave output to file.');
        $this->addOption('class', 'c', InputOption::VALUE_REQUIRED, 'Parser class name.', 'YaccParser');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return void
     */
    public function execute(InputInterface $input, OutputInterface $output): void
    {
        $this->validateInput($input);

        $grammar = $input->getArgument('grammar');
        $skeleton = $input->getOption('skeleton');
        $class = $input->getOption('class');
        $result = $input->getOption('output') ?? \sprintf('.%s%s.php', DIRECTORY_SEPARATOR, $class);

        $context = new Context(\basename($grammar));
        $context->className = $class;

        $this->generate($context, $grammar, $skeleton, $result);
        $output->writeln('<info>OK</info>');
    }

    /**
     * @param InputInterface $input
     *
     * @return void
     */
    private function validateInput(InputInterface $input): void
    {
        $skeleton = $input->getOption('skeleton');
        if ($skeleton === null || !\file_exists($skeleton)) {
            throw new InvalidOptionException(\sprintf('The skeleton file "%s" is not found', $skeleton));
        }

        $grammar = $input->getArgument('grammar');
        if (!\file_exists($grammar)) {
            throw new InvalidArgumentException(\sprintf('The grammar file "%s" is not found', $grammar));
        }
    }

    /**
     * @param Context $context
     * @param string  $grammar
     * @param string  $skeleton
     * @param string  $result
     *
     * @return void
     */
    private function generate(Context $context, string $grammar, string $skeleton, string $result): void
    {
        $generator = new Generator();

        try {
            $generator->generate($context, \file_get_contents($grammar), \file_get_contents($skeleton), $result);
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
