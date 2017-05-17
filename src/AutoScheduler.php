<?php
class AutoScheduler
{

    const GOOGLE_AUTH_CONFIG_PATH = '/../data/auth/service-account.json';

    const GOOGLE_CALENDAR_SCOPES = Google_Service_Calendar::CALENDAR;

    const GOOGLE_CALENDAR_ID = 'primary';

    const DEFAULT_TIMEZONE = 'Africa/Cairo';

    /**
     * @var Google_Client
     */
    private $client;

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    function getClient()
    {
        if (!$this->client) {
            $this->client = new Google_Client();
            $this->client->setScopes(self::GOOGLE_CALENDAR_SCOPES);
            $this->client->setAuthConfig(__DIR__ . self::GOOGLE_AUTH_CONFIG_PATH);
        }
        return $this->client;
    }

    function createEvent($name, $attendees, $times)
    {
        // Get the API client and construct the service object.
        $client = $this->getClient();
        $service = new Google_Service_Calendar($client);

        $attendeesData = [];
        foreach ((array)$attendees as $attendee) {
            $attendeesData[] = ['email' => $attendee];
        }

        $startTime = null;
        $endTime = null;
        foreach ((array)$times as $time) {
            $timeData = [
                'dateTime' => date('c', strtotime($time)),
                'timeZone' => self::DEFAULT_TIMEZONE,
            ];
            if (!$startTime) {
                $startTime = $timeData;
            } else if (!$endTime) {
                $endTime = $timeData;
            } else {
                throw \Exception('Maximum of 2 times permitted (start & end times).');
            }
        }
        if (!$endTime) {
            $endTime = $startTime;
        }

        // Create event
        $newEvent = new Google_Service_Calendar_Event([
            'summary' => $name,
            'start' => $startTime,
            'end' => $endTime,
            'attendees' => $attendeesData,
        ]);

        $service->events->insert(self::GOOGLE_CALENDAR_ID, $newEvent);

        return true;
    }

    function listEvents()
    {
        // Get the API client and construct the service object.
        $client = $this->getClient();
        $service = new Google_Service_Calendar($client);

        // List last 100 events
        $optParams = [
            'maxResults' => 100,
            'orderBy' => 'startTime',
            'singleEvents' => true,
        ];
        $results = $service->events->listEvents(self::GOOGLE_CALENDAR_ID, $optParams);

        return $results->getItems();
    }

}
