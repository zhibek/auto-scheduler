<?php
class AutoScheduler
{

    const GOOGLE_AUTH_CONFIG_PATH = '/../data/auth/service-account.json';

    const GOOGLE_CALENDAR_SCOPES = Google_Service_Calendar::CALENDAR;

    const GOOGLE_CALENDAR_ID = 'primary';

    const DEFAULT_TIMEZONE = 'Africa/Cairo';

    const EVENTS_PATH = '/../data/event-templates/*.yaml';

    const STATE_PATH = '/../data/state.json';

    /**
     * @var Google_Client
     */
    private $client;

    private $state;

    public function __construct()
    {
        date_default_timezone_set(self::DEFAULT_TIMEZONE);
    }

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    private function getClient()
    {
        if (!$this->client) {
            if (!is_file(__DIR__ . self::GOOGLE_AUTH_CONFIG_PATH)) {
                throw new Exception(sprintf('Google Auth Config required, but not found at "%s"', __DIR__ . self::GOOGLE_AUTH_CONFIG_PATH));
            }

            $this->client = new Google_Client();
            $this->client->setScopes(self::GOOGLE_CALENDAR_SCOPES);
            $this->client->setAuthConfig(__DIR__ . self::GOOGLE_AUTH_CONFIG_PATH);
        }
        return $this->client;
    }

    private function getState()
    {
        if (!$this->state) {
            if (is_file(__DIR__ . self::STATE_PATH)) {
                $this->state = json_decode(file_get_contents(__DIR__ . self::STATE_PATH));
            } else {
                $this->state = (object)[];
            }
        }
        return $this->state;
    }

    private function saveState()
    {
        $state = $this->getState();

        file_put_contents(__DIR__ . self::STATE_PATH, json_encode($state));

        return true;
    }

    public function createEvent($subject, $recipients, $times)
    {
        // Get the API client and construct the service object.
        $client = $this->getClient();
        $service = new Google_Service_Calendar($client);

        $recipientsData = [];
        foreach ((array)$recipients as $recipient) {
            $recipientsData[] = ['email' => $recipient];
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
            'summary' => $subject,
            'start' => $startTime,
            'end' => $endTime,
            'attendees' => $recipientsData,
            'guestsCanModify' => true,
        ]);

        $service->events->insert(self::GOOGLE_CALENDAR_ID, $newEvent);

        return true;
    }

    public function listEvents()
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

    public function flushEvents()
    {
        // Get the API client and construct the service object.
        $client = $this->getClient();
        $service = new Google_Service_Calendar($client);

        $events = $this->listEvents();

        foreach ($events as $event) {
            $service->events->delete(self::GOOGLE_CALENDAR_ID, $event->id);
        }

        return true;
    }

    public function fetchEventTemplates()
    {
        $eventTemplates = [];

        foreach (glob(__DIR__ . self::EVENTS_PATH) as $eventTemplate) {
            $eventTemplates[] = Symfony\Component\Yaml\Yaml::parse(file_get_contents($eventTemplate));
        }

        return $eventTemplates;
    }

}
