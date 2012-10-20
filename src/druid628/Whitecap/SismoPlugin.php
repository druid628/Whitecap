<?php

/*
 * This file is part of the Whitecap utility.
 *
 * (c) Micah Breedlove <druid628@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace druid628\Whitecap;

use Philip\AbstractPlugin as BasePlugin;
use Philip\IRC\Response;

/**
 * Adds Sismo (Continuous Integration) functionality to the Philip IRC bot.
 *
 * @author Micah Breedlove <druid628@gmail.com>
 */
class SismoPlugin extends BasePlugin
{
    /**
     * Adds !status, !projects, and !build commands to the bot:
     *
     * !status <project-slug> -- Tells the bot to get the status of a project
     * Example usage:
     *      !status whitecap
     *
     * !projects
     * Example usage:
     *      !projects
     *
     * !build <project-slug> -- Tells the bot to build a project and return
     *        the status
     * Example usage:
     *      !build whitecap
     *
     */
    public function init()
    {
        $bot    = $this->bot;
        $config = $bot->getConfig();
        $sismo  = $config['sismo'];

        $this->bot->onChannel("/^!status ([\w-_]*)/", function($event) use ($sismo) {
            // execute Sismo stuff
            $matches = $event->getMatches();
            $project = $sismo->getProject($matches[0]);
            $output = sprintf("SISMO: %s build status: %s", $project->getName(), $project->getStatus());

            $event->addResponse( Response::msg($event->getRequest()->getSource(), $output) );
        });


        $this->bot->onChannel("/^!projects/", function($event) use ($sismo) {
            $event->addResponse( Response::msg($event->getRequest()->getSource(), "SISMO: The following projects are available.") );
            $event->addResponse( Response::msg($event->getRequest()->getSource(), "   Project (slug)") );
            // execute Sismo stuff
            foreach($sismo->getProjects() as $project) {
                $event->addResponse( Response::msg($event->getRequest()->getSource(), sprintf(" -- %s [ %s ]", $project->getName(), $project->getSlug())) );
            }

        });

        $this->bot->onChannel("/^!build ([\w-_]*)/", function($event) use ($sismo) {
            // execute Sismo stuff
            $matches = $event->getMatches();
            $project = $sismo->getProject($matches[0]);
            $sismo->build($project);

            while($project->isBuilding()) {
            }
            $output = sprintf("SISMO: %s build status: %s", $project->getName(), $project->getStatus());
            $event->addResponse( Response::msg($event->getRequest()->getSource(), $output) );

        });

    }
}
