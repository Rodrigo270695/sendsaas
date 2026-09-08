<?php

it('redirects the home page to login', function () {
    $this->get('/')
        ->assertRedirect('/login');
});
