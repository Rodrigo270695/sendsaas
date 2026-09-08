<?php

test('registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register')->assertNotFound();
});
