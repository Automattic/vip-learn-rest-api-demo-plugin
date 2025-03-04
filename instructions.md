## Step 1

In this folder I would like you to scaffold a plugin called "Live Updates" which is a "live-blogging" plugin will be an example plugin to demonstrate advanced use of the WordPress Rest API.

The root folder is already created.

The Plugin will provide some REST API endpoints starting with "liveupdates/<post id>" which will be the default GET route to provide the 20 most recent published posts of a CPT "live-update", which relate to a post ID (any post type).

The plugin provides the CPT "live-update".

also within this plugin, I want a folder "examples" where I take some of the code from this plugin to use in a course that I am writing, along with some wordpress playground blueprints to allow code to be experimented with.

# Details

This plugin is authored by VIP Learn
The version should be 0.1.0
author URL is learn.wpvip.com
I would like to use namespaces throughout the php files
I will be using git, so set up a .gitignore file which ignore node_modules
I am building this a demo plugin to go alongside an online course I am writing - I will iterate on this cocebase and like to version the plugin and tag commits at each step, and need to know how to do this.

## Step 2

There will also be a front-end REACT app which can be added as a gutenberg block (as a dynamic block, registered in php), and allows the editor to select any post id (for now this could be a search on standard wordpress posts, but we will possibly allow other allowed post types in the future, so add a filter for this, to make the allowed post types customisable). This will display a list of live updates, live blog style, on the front-end of the website.

The plugin should contain a subfolder for the react app with all the relevant files needed to compile the react app, run in development mode, build etc., with the usual src and build directories

The react app will poll for updates every 30 seconds and will do so by requesting a new api GET endpoint liveupdates/<post id>/<timestamp>. The timestamp in the url will be strictly in 30 second increments at 00 and 30 seconds of each minute, and will fetch only published live-update posts published after the timestamp.

## Step 3

To make it more scaleable In the react app, the polling increment in the react appo should have some random jitter up to + 20 seconds, to help space out the requests at the server, but the requested URL should stick to the 00 and 30 second intervals to take advnange of caching of the endpoints.