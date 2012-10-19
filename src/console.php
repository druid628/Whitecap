<?php

/*
 * This file is part of the Eyjafjallajokull utility.
 *
 * (c) Micah Breedlove <druid628@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use druid628\Eyjafjallajokull\Eyjafjallajokull;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;
use Philip\Philip;
use Philip\IRC\Response;
use Symfony\Component\Process\Process;

require_once(__DIR__."/config/philip-config.php");
$console = new Application("Eyjafjallajokull", Eyjafjallajokull::VERSION);

$console->register("philip:start")
    ->setDescription('Initialize PHiliP IRC Bot')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {


            /*
             * I'm not exactly stoked about this.
             * Unfortunately until @epochblue or myself
             * can figure out how to make philip return
             * back to prompt or fork or otherwise be a
             * long running process, this will have to
             * do.
             *
             * -- @druid628
             */
            try {
              $philip_cmd = "nohup php philip.php &";
              $process = new Process($philip_cmd, sprintf("%s/bin/", __DIR__));
              $philipPid= getmypid();
              $process->setTimeout(1);
              $process->run();
            } catch(Exception $e) {
              if($e instanceof RuntimeException)
              {
                $output->writeln("  philip:  Started  ");
                return true;
              }
            }
    })
;

$console->register("philip:stop")
    ->setDescription('Stop PHiliP IRC Bot')
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app, $config) {
        if(isset($config['pid']))
        {
            $pidFile = $config['pid'];
            $fh = fopen($pidFile, 'r');
            $pid = fread($fh, filesize($pidFile));
            fclose($fh);
            $process = new Process(sprintf("kill -9 %s", $pid));
            $process->run();
            unlink($pidFile);
            $output->writeln("  philip:  Stopped  ");
        }

    })
;

$console->register("sismo:projects")
    ->setDescription('List available projects')
    ->setHelp(<<<EOF
The <info>%command.name%</info> command displays the available projects Sismo can build.
EOF
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
         $sismo = $app['sismo'];

        $projects = array();
        $width = 0;
        foreach ($sismo->getProjects() as $slug => $project) {
            $projects[$slug] = $project->getName();
            $width = strlen($project->getName()) > $width ? strlen($project->getName()) : $width;
        }
        $width += 2;

        $output->writeln('');
        $output->writeln('<comment>Available projects:</comment>');
        foreach ($projects as $slug => $project) {
            $output->writeln(sprintf("  <info>%-${width}s</info> %s", $slug, $project));
        }

        $output->writeln('');
    })
;

//
$console
    ->register('sismo:output')
    ->setDefinition(array(
        new InputArgument('slug', InputArgument::REQUIRED, 'Project slug'),
    ))
    ->setDescription('Displays the latest output for a project')
    ->setHelp(<<<EOF
The <info>%command.name%</info> command displays the latest output for a project:

    <info>php %command.full_name% twig</info>
EOF
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        $sismo = $app['sismo'];
        $slug = $input->getArgument('slug');
        if (!$sismo->hasProject($slug)) {
            $output->writeln(sprintf('<error>Project "%s" does not exist.</error>', $slug));

            return 1;
        }

        $project = $sismo->getProject($slug);

        if (!$project->getLatestCommit()) {
            $output->writeln(sprintf('<error>Project "%s" has never been built yet.</error>', $slug));

            return 2;
        }

        $output->write($project->getLatestCommit()->getOutput());

        $now = new \DateTime();
        $diff = $now->diff($project->getLatestCommit()->getBuildDate());
        if ($m = $diff->format('%i')) {
            $time = $m.' minutes';
        } else {
            $time = $diff->format('%s').' seconds';
        }
        $output->writeln('');
        $output->writeln(sprintf('<info>This output was generated by Sismo %s ago</info>', $time));
    })
;


$console
    ->register('sismo:build')
    ->setDefinition(array(
        new InputArgument('slug', InputArgument::OPTIONAL, 'Project slug'),
        new InputArgument('sha', InputArgument::OPTIONAL, 'Commit sha'),
        new InputOption('force', '', InputOption::VALUE_NONE, 'Force the build'),
        new InputOption('local', '', InputOption::VALUE_NONE, 'Disable remote sync'),
        new InputOption('silent', '', InputOption::VALUE_NONE, 'Disable notifications'),
        new InputOption('timeout', '', InputOption::VALUE_REQUIRED, 'Time limit'),
        new InputOption('data-path', '', InputOption::VALUE_REQUIRED, 'The data path'),
        new InputOption('config-file', '', InputOption::VALUE_REQUIRED, 'The config file'),
    ))
    ->setDescription('Build projects')
    ->setHelp(<<<EOF
Without any arguments, the <info>%command.name%</info> command builds the latest commit
of all configured projects one after the other:

    <info>php %command.full_name%</info>

The command loads project configurations from
<comment>~/.sismo/config.php</comment>. Change it with the
<info>--config-file</info> option:

    <info>php %command.full_name% --config-file=/path/to/config.php</info>

Data (repository, DB, ...) are stored in <comment>~/.sismo/data/</comment>.
The <info>--data-path</info> option allows you to change the default:

    <info>php %command.full_name% --data-path=/path/to/data</info>

Pass the project slug to build a specific project:

    <info>php %command.full_name% twig</info>

Force a specific commit to be built by passing the SHA:

    <info>php %command.full_name% twig a1ef34</info>

Use <comment>--force</comment> to force the built even if it has already been
built previously:

    <info>php %command.full_name% twig a1ef34 --force</info>

Disable notifications with <comment>--silent</comment>:

    <info>php %command.full_name% twig a1ef34 --silent</info>

Disable repository synchonization with <comment>--local</comment>:

    <info>php %command.full_name% twig a1ef34 --local</info>

Limit the time (in seconds) spent by the command building projects by using
the <comment>--timeout</comment> option:

    <info>php %command.full_name% twig --timeout 3600</info>

When you use this command as a cron job, <comment>--timeout</comment> can avoid
the command to be run concurrently. Be warned that this is a rough estimate as
the time is only checked between two builds. When a build is started, it won't
be stopped if the time limit is over.

Use the <comment>--verbose</comment> option to debug builds in case of a
problem.
EOF
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($app) {
        if ($input->getOption('data-path')) {
            $app['data.path'] = $input->getOption('data-path');
        }
        if ($input->getOption('config-file')) {
            $app['config.file'] = $input->getOption('config-file');
        }
        $sismo = $app['sismo'];

        if ($slug = $input->getArgument('slug')) {
            if (!$sismo->hasProject($slug)) {
                $output->writeln(sprintf('<error>Project "%s" does not exist.</error>', $slug));

                return 1;
            }

            $projects = array($sismo->getProject($slug));
        } else {
            $projects = $sismo->getProjects();
        }

        $start = time();
        $startedOut = false;
        $startedErr = false;
        $callback = null;
        if (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity()) {
            $callback = function ($type, $buffer) use ($output, &$startedOut, &$startedErr) {
                if ('err' === $type) {
                    if (!$startedErr) {
                        $output->write("\n<bg=red;fg=white> ERR </> ");
                        $startedErr = true;
                        $startedOut = false;
                    }

                    $output->write(str_replace("\n", "\n<bg=red;fg=white> ERR </> ", $buffer));
                } else {
                    if (!$startedOut) {
                        $output->write("\n<bg=green;fg=white> OUT </> ");
                        $startedOut = true;
                        $startedErr = false;
                    }

                    $output->write(str_replace("\n", "\n<bg=green;fg=white> OUT </> ", $buffer));
                }
            };
        }

        $flags = 0;
        if ($input->getOption('force')) {
            $flags = $flags | Sismo::FORCE_BUILD;
        }
        if ($input->getOption('local')) {
            $flags = $flags | Sismo::LOCAL_BUILD;
        }
        if ($input->getOption('silent')) {
            $flags = $flags | Sismo::SILENT_BUILD;
        }

        foreach ($projects as $project) {
            // out of time?
            if ($input->getOption('timeout') && time() - $start > $input->getOption('timeout')) {
                break;
            }

            try {
                $output->writeln(sprintf('<info>Building Project "%s" (into "%s")</info>', $project, $app['builder']->getBuildDir($project)));
                $sismo->build($project, $input->getArgument('sha'), $flags, $callback);

                $output->writeln('');
            } catch (BuildException $e) {
                $output->writeln("\n".sprintf('<error>%s</error>', $e->getMessage()));

                return 1;
            }
        }
    })
;

/**
 * sismo:run is commented out until I (@druid628) force an upgrade to php 5.4
 * which is likely to happen Mid-Late Oct 2012
 *
 */
/*
$console
    ->register('sismo:run')
    ->setDefinition(array(
        new InputArgument('address', InputArgument::OPTIONAL, 'Address:port', 'localhost:9000')
    ))
    ->setDescription('Runs Sismo with PHP built-in web server')
    ->setHelp(<<<EOF
The <info>%command.name%</info> command runs the embedded Sismo web server:

    <info>%command.full_name%</info>

You can also customize the default address and port the web server listens to:

    <info>%command.full_name% 127.0.0.1:8080</info>
EOF
    )
    ->setCode(function (InputInterface $input, OutputInterface $output) use ($console) {

        if (version_compare(PHP_VERSION, '5.4.0') < 0) {
            throw new \Exception('This feature only runs with PHP 5.4.0 or higher.');
        }

        $sismo = __DIR__ . '/sismo.php';
        while (!file_exists($sismo)) {
            $dialog = $console->getHelperSet()->get('dialog');
            $sismo = $dialog->ask($output, sprintf('<comment>I cannot find "%s". What\'s the absoulte path of "sismo.php"?</comment> ', $sismo), __DIR__ . '/sismo.php');
        }

        $output->writeln(sprintf("Sismo running on <info>%s</info>\n", $input->getArgument('address')));

        $builder = new ProcessBuilder(array(PHP_BINARY, '-S', $input->getArgument('address'), $sismo));

        $builder->setWorkingDirectory(getcwd());
        $builder->setTimeout(null);
        $builder->getProcess()->run(function ($type, $buffer) use ($output) {
            if (OutputInterface::VERBOSITY_VERBOSE === $output->getVerbosity()) {
                $output->write($buffer);
            }
        });
    })
;
*/



//
return $console;
