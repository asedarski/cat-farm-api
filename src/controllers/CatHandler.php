<?php

use DominionEnterprises\Filterer;
use Slim\Http\Request;
use Slim\Http\Response;

class CatHandler
{
    /**
     * @var \PDO
     */
    protected $db;

    /**
     * @var \Monolog\Logger
     */
    protected $logger;

    /**
     * @param \Monolog\Logger
     * @param \PDO
     */
    public function __construct(Monolog\Logger $logger, PDO $db)
    {
        $this->logger = $logger;
        $this->db = $db;
    }

    /**
     * Creats a cat on the farm
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * 
     * @return Response The appropriate response for the given request
     */
    public function create(Request $request, Response $response) : Response
    {
        $this->logger->addInfo('Create cat requested');
        $data = $request->getParsedBody();
        $filters = [
            'name' => ['required' => true, ['string']],
            'age' => [['uint', true]],
            'status' => ['required' => true, ['string']],
            'temperment' => [['string', true]],
            'photoUrls' => ['default' => [], ['array'], ['ofScalars', [['string']]]],
        ];
        list($filterSuccess, $filteredParams, $error) = Filterer::filter($filters, $data);

        if (!$filterSuccess) {
            $this->logger->addInfo('Create cat failed - ' . $error);
            return $response->withStatus(400)->write('Bad Request - ' . $error);
        }

        $sql = "INSERT INTO cat (name, age, status, temperment, photoUrls) VALUES (:name, :age, :status, :temperment, :photoUrls)";

        $statement = $this->db->prepare($sql);
        $statement->bindValue(':name', $filteredParams['name']);
        $statement->bindValue(':age', $filteredParams['age'] ?? null);
        $statement->bindValue(':status', $filteredParams['status']);
        $statement->bindValue(':temperment', $filteredParams['temperment'] ?? null);
        $photoUrls = isset($filteredParams['photoUrls']) ? json_encode($filteredParams['photoUrls']) : null;
        $statement->bindValue(':photoUrls', $photoUrls);

        $inserted = $statement->execute();
        $catId = $this->db->lastInsertId();

        if ($inserted) {
            $this->logger->addInfo('Cat ' . $catId . ' created');
            return $response->withStatus(201)->withJson($this->findCatById($catId));
        }

        $this->logger->addInfo('Cat creation failed - something went wrong');
        return $response->withStatus(400)->write('Something went wrong');
    }

    /**
     * Indexes the cats based on optional parameters
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * 
     * @return Response The appropriate response for the given request
     */
    public function index(Request $request, Response $response) : Response
    {   
        $this->logger->addInfo('Index cats requested');
        $queryParameters = $request->getQueryParams();
        $filters = [
            'status' => [['string'], ['strtolower']],
            'name' => [['string'], ['strtolower']],
        ];

        list ($filterSuccess, $filteredParams, $error) = Filterer::filter($filters, $queryParameters);

        if (!$filterSuccess) {
            $this->logger->addInfo('Index cats failed with a bad request - ' . $error);
            return $response->withStatus(400)->write('Bad Request - '. $error);
        }

        $status = $filteredParams['status'] ?? null;
        $name = $filteredParams['name'] ?? null;

        $sql = "SELECT id, name, age, status, temperment, photoUrls FROM cat 
                WHERE (status = :status or :status is null) 
                AND (name = :name or :name is null)";

        $statement = $this->db->prepare($sql);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':name', $name);

        $statement->execute();

        $result = $statement->fetchAll();
        $this->logger->addInfo('Index cats successful');

        return $response->withStatus(200)->withJson($result);
    }

    /**
     * Gets a single cat
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * 
     * @return Response The appropriate response for the given request
     */
    public function get(Request $request, Response $response) : Response
    {
        $this->logger->addInfo('Get cat requested');
        $route = $request->getAttribute('route');
        $catId = $route->getArgument('catId');

        $cat = $this->findCatById($catId);

        if (empty($cat)) {
            $this->logger->addInfo('Get cat failed - id ' . $catId . ' not found');
            return $response->withStatus(404)->write('Cat ' . $catId . ' not found on the farm');
        }

        $this->logger->addInfo('Get cat successful for id ' . $catId);
        return $response->withStatus(200)->withJson($cat);
    }

    /**
     * Updates a cat on the farm
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * 
     * @return Response The appropriate response for the given request
     */
    public function update(Request $request, Response $response) : Response
    {
        $this->logger->addInfo('Update cat requested');
        $route = $request->getAttribute('route');
        $catId = $route->getArgument('catId');
        $data = $request->getParsedBody();
        $filters = [
            'name' => [['string']],
            'age' => [['uint', true]],
            'status' => [['string']],
            'temperment' => [['string', true]],
            'photoUrls' => ['default' => [], ['array'], ['ofScalars', [['string']]]],
        ];
        list($filterSuccess, $filteredParams, $error) = Filterer::filter($filters, $data);

        if (!$filterSuccess) {
            $this->logger->addInfo('Update cat failed for id ' . $catId . ' - ' . $error);
            return $response->withStatus(400)->write('Bad Request for id ' . $catId . ' - ' . $error);
        }

        $cat = $this->findCatById($catId);

        if (empty($cat)) {
            $this->logger->addInfo('Update cat failed - id ' . $catId . ' not found');
            return $response->withStatus(404)->write('Cat ' . $catId . ' not found on the farm');
        }

        $filteredParams['photoUrls'] = json_encode($filteredParams['photoUrls']);

        $updateFields = [];
        $setString = '';

        foreach ($filteredParams as $field => $value) {
            $setString .= "`$field` = :$field,";
        }

        $setString = rtrim($setString, ',');
        $filteredParams['id'] = $catId;
        $sql = "UPDATE cat SET $setString WHERE id = :id";
        $statement = $this->db->prepare($sql);
        
        $updated = $statement->execute($filteredParams);

        if ($updated) {
            $this->logger->addInfo('Cat updated for id ' . $catId);
            return $response->withStatus(200)->withJson($this->findCatById($catId));
        }

        $this->logger->addInfo('Cat update for id ' . $catId . ' failed - something went wrong');
        return $response->withStatus(400)->write('Cat update for id ' . $catId . ' failed - something went wrong');
    }

    /**
     * Deletes a cat from the farm
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * 
     * @return Response The appropriate response for the given request
     */
    public function delete(Request $request, Response $response) : Response
    {
        $this->logger->addInfo('Delete cat requested');
        $route = $request->getAttribute('route');
        $catId = $route->getArgument('catId');

        $cat = $this->findCatById($catId);

        if (empty($cat)) {
            $this->logger->addInfo('Delete cat failed - id ' . $catId . ' not found');
            return $response->withStatus(404)->write('Cat ' . $catId . ' not found on the farm');
        }

        $sql = "DELETE FROM cat WHERE id = :catId";
        $statement = $this->db->prepare($sql);
        $statement->bindValue(':catId', $catId);
        $deleted = $statement->execute();

        if ($deleted) {
            $this->logger->addInfo('Cat deleted');
            return $response->withStatus(204)->write('Deleted - Cat ' . $catId . ' removed');
        }

        $this->logger->addInfo('Cat delete for id ' . $catId . ' failed - something went wrong');
        return $response->withStatus(400)->write('Cat delete for id ' . $catId . ' failed - something went wrong');
    }

    /**
     * Feed a cat on the farm
     * 
     * @param Request $request The request object
     * @param Response $response The response object
     * 
     * @return Response The appropriate response for the given request
     */
    public function feed(Request $request, Response $response) : Response
    {
        $this->logger->addInfo('Feed cat requested');
        $route = $request->getAttribute('route');
        $catId = $route->getArgument('catId');

        $cat = $this->findCatById($catId);

        if (empty($cat)) {
            $this->logger->addInfo('Feed cat failed - id ' . $catId . ' not found');
            return $response->withStatus(404)->write('Cat ' . $catId . ' not found on the farm');
        }

        if ($cat['status'] !== 'hungry') {
            $this->logger->addInfo('Cat is not hungry');
            return $response->withStatus(400)->write('Cat ' . $catId . ' is not hungry. Please do not feed it.');
        }

        $sql = "UPDATE cat SET status = 'content' WHERE id = :catId";
        $statement = $this->db->prepare($sql);
        $statement->bindValue(':catId', $catId);
        $fed = $statement->execute();

        if ($fed) {
            $this->logger->addInfo('Cat has been fed');
            return $response->withStatus(204)->write('No Content - Cat ' . $catId . ' recorded as fed');
        }

        $this->logger->addInfo('Cat feeding for id ' . $catId . ' failed - something went wrong');
        return $response->withStatus(400)->write('Cat feeding for id ' . $catId . ' failed - something went wrong');
    }

    private function findCatById($catId)
    {
        $sql = "SELECT id, name, age, status, temperment, photoUrls FROM cat WHERE id = :catId";

        $statement = $this->db->prepare($sql);
        $statement->bindValue(':catId', $catId);

        $statement->execute();
        $cat = $statement->fetch();

        return $cat;
    }
}
