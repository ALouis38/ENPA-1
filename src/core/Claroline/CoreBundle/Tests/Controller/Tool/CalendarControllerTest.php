<?php

namespace Claroline\CoreBundle\Controller\Tool;

use Claroline\CoreBundle\Library\Testing\FunctionalTestCase;

class CalendarControllerTest extends FunctionalTestCase
{

    protected function setUp()
    {
        parent::setUp();
        $this->loadPlatformRoleData();
        $this->loadUserData(array('ws_creator' => 'ws_creator'));
        $this->loadWorkspaceData(array('ws_a' => 'ws_creator'));
    }

    public function testWorkspaceUserCanSeeTheAgenda()
    {
        $workspaceId = $this->getFixtureReference('workspace/ws_a')->getId();
        $this->logUser($this->getFixtureReference('user/ws_creator'));
        $this->client->request('GET', "/workspaces/{$workspaceId}/open/tool/calendar");
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertEquals(200, $status);
    }

    public function testShowWorkspaceCalendar()
    {
        $workspaceId = $this->getFixtureReference('workspace/ws_a')->getId();
        $this->logUser($this->getFixtureReference('user/ws_creator'));
        $this->client->request(
            'POST',
            "/workspaces/tool/calendar/{$workspaceId}/add",
            array(
                'calendar_form' => array(
                    'title' => 'foo',
                    'end' => '22-02-2013',
                    'description' => 'ghhkkgf',
                    'allDay' => true,
                    'priority' => '#01A9DB'
                   ),
                  'date' => 'Thu Jan 24 2013 00:00:00 GMT+0100'
                )
        );
        $status = $this->client->request('GET', "/workspaces/tool/calendar/{$workspaceId}/show");
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertEquals(200, $status);
    }

    public function testAddEventCalendar()
    {
        $workspaceId = $this->getFixtureReference('workspace/ws_a')->getId();
        $this->logUser($this->getFixtureReference('user/ws_creator'));
        $this->client->request(
            'POST',
            "/workspaces/tool/calendar/{$workspaceId}/add",
            array(
                'calendar_form' => array(
                    'title' => 'foo',
                   'end' => '22-02-2013',
                    'description' => 'ghhkkgf',
                    'allDay' => true,
                    'priority' => '#01A9DB'
                   ),
                  'date' => 'Thu Jan 24 2013 00:00:00 GMT+0100'
                )
        );
        $content = $this->client->getResponse()->getContent();
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertEquals(200, $status);
    }

    public function testDeleteEventCalendar()
    {
        $workspaceId = $this->getFixtureReference('workspace/ws_a')->getId();
        $this->logUser($this->getFixtureReference('user/ws_creator'));
        $this->client->request(
            'POST',
            "/workspaces/tool/calendar/{$workspaceId}/add",
            array(
                'calendar_form' => array(
                    'title' => 'foo',
                    'description' => 'ghhkkgf',
                    'end' => '22-02-2013',
                    'allDay' => true
                   ),
                  'date' => 'Thu Jan 24 2013 00:00:00 GMT+0100'
                )
        );

        $data = $this->client->getResponse()->getContent();
        $data = json_decode($data, true);
        $this->client->request(
            'POST',
            "/workspaces/tool/calendar/{$workspaceId}/delete",
            array(
                    'id' => $data['id']
                )
        );

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertEquals(200, $status);

    }

    public function testMoveEventCalendar()
    {
        $workspaceId = $this->getFixtureReference('workspace/ws_a')->getId();
        $this->logUser($this->getFixtureReference('user/ws_creator'));
        $this->client->request(
            'POST',
            "/workspaces/tool/calendar/{$workspaceId}/add",
            array(
                'calendar_form' => array(
                    'title' => 'foo',
                    'description' => 'ghhkkgf',
                    'end' => '22-02-2013',
                    'allDay' => true
                   ),
                  'date' => 'Thu Jan 24 2013 00:00:00 GMT+0100'
                )
        );
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $dataForm = array(
            'id' => $content['id'],
            'dayDelta' => '1',
            'minuteDelta' => '0'
        );
        $this->client->request(
            'POST',
            "/workspaces/tool/calendar/{$workspaceId}/move",
            $dataForm
        );

        $status = $this->client->getResponse()->getStatusCode();
        $this->assertEquals(200, $status);

    }

    public function testUpdateEvent()
    {
        $workspaceId = $this->getFixtureReference('workspace/ws_a')->getId();
        $this->logUser($this->getFixtureReference('user/ws_creator'));
        $this->client->request(
            'POST',
            "/workspaces/tool/calendar/{$workspaceId}/add",
            array(
                'calendar_form' => array(
                    'title' => 'foo',
                    'description' => 'ghhkkgf',
                    'end' => '22-02-2013',
                    'allDay' => true,
                    'priority' => '#01A9DB'
                   ),
                  'date' => 'Thu Jan 24 2013 00:00:00 GMT+0100'
                )
        );
        $status = $this->client->getResponse()->getStatusCode();
        $this-> assertEquals(200, $status);
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->client->request(
            'POST',
            "/workspaces/tool/calendar/{$workspaceId}/update",
            array(
                'calendar_form' => array(
                'title' => 'foo',
                'description' => 'ghhkkgf',
                'end' => '22-02-2013',
                'allDay' => true,
                'priority' => '#01A9DB'
                ),
                'id' => $content['id']
                )
        );
        $status = $this->client->getResponse()->getStatusCode();
        $this->assertEquals(200, $status);
    }
}
