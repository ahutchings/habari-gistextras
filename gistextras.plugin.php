<?php

class GistExtras extends Plugin
{
    public function info()
    {
        return array(
            'url' => 'http://andrewhutchings.com',
            'name' => 'Gist Extras',
            'description' => 'Caches and embeds gists in post content.',
            'license' => 'Apache License 2.0',
            'author' => 'Andrew Hutchings',
            'authorurl' => 'http://andrewhutchings.com',
            'version' => '0.0.2'
        );
    }

    public function action_update_check()
    {
        Update::add( 'GistExtras', 'A6A9D42C-2F4F-11DE-BB63-026155D89593', $this->info->version );
    }

    public function filter_post_content_out($content)
    {
        return $this->embed_gists($content);
    }

    public function filter_post_excerpt_out($excerpt)
    {
        return $this->embed_gists($excerpt);
    }

    public function set_priorities()
    {
        return array(
          'filter_post_content_out' => 10,
          'filter_post_excerpt_out' => 10
        );
    }

    private function process_gist($gist)
    {
        // remove document.writes
        $gist = preg_replace('/document.write\(\'/i', '', $gist);
        $gist = preg_replace('/(*ANYCRLF)\'\)$/m', '', $gist);

        // remove javascript newlines
        $gist = preg_replace('%(?<!/)\\\\n%', '', $gist);

        // reverse javascript escaping
        $gist = stripslashes($gist);

        // remove line breaks
        $gist = preg_replace("/[\n\r]/", '', $gist);

        if (Options::get('gistextras__usecustomcss')) {
            $gist = str_replace('http://gist.github.com/stylesheets/gist/embed.css', Options::get('gistextras__customcssurl'), $gist);
        }

        return $gist;
    }

    private function embed_gists($text)
    {
        $gists_regex = '/<script[^>]+src="(http:\/\/gist.github.com\/[^"]+)"[^>]*><\/script>/i';

        preg_match_all($gists_regex, $text, $gists);

        for ($i = 0, $n = count($gists[0]); $i < $n; $i++) {

            if (Options::get('gistextras__cachegists')) {
                if (Cache::has($gists[1][$i])) {
                    $gist = Cache::get($gists[1][$i]);
                } else {
                    if ($gist = RemoteRequest::get_contents($gists[1][$i])) {
                        $gist = $this->process_gist($gist);
                        Cache::set($gists[1][$i], $gist, 86400); // cache for 1 day
                    }
                }
            } else {
                $gist = RemoteRequest::get_contents($gists[1][$i]);
                $gist = $this->process_gist($gist);
            }

            // replace the script tag
            $text = str_replace($gists[0][$i], $gist, $text);
        }

        return $text;
    }

    public function filter_plugin_config($actions, $plugin_id)
    {
        if ($plugin_id == $this->plugin_id()) {
            $actions[]= _t('Configure');
        }

        return $actions;
    }

    public function action_plugin_ui($plugin_id, $action)
    {
        if ( $plugin_id == $this->plugin_id() ) {
            switch ($action) {
                case _t('Configure'):
                    $form = new FormUI(strtolower(get_class($this)));
                    $form->append('checkbox', 'cache', 'gistextras__cachegists', _t('Cache Gists'));
                    $form->append('checkbox', 'usecustomcss', 'gistextras__usecustomcss', _t('Use custom CSS'));
                    $form->append('text', 'csspath', 'gistextras__customcssurl', _t('Custom CSS URL'));
                    $form->append('submit', 'save', 'Save');
                    $form->out();
                break;
            }
        }
    }
}

?>
