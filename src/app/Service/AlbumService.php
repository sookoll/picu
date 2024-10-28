<?php

namespace App\Service;

use App\Enum\ItemTypeEnum;
use App\Enum\ProviderEnum;
use App\Model\Album;
use App\Model\Photo;
use App\Model\Provider;
use Exception;
use JsonException;
use PDO;
use PDOException;
use Psr\Container\ContainerInterface;

class AlbumService extends BaseService
{
    public function __construct(
        ContainerInterface $container,
        protected readonly ItemService $itemService,
    )
    {
        parent::__construct($container);
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
        $this->itemService->setBaseUrl($baseUrl);
    }

    /**
     * @param Provider|null $provider
     * @param string|null $id
     * @param string|null $fid
     * @param bool $onlyPublic
     * @return Album[]
     */
    public function getList(
        Provider $provider = null,
        string $id = null,
        string $fid = null,
        bool $onlyPublic = false,
    ): array
    {
        $where = [
            '1' => ['=', '1']
        ];
        $params = [];

        if ($provider) {
            $where['pa.provider'] = ['=', ':provider'];
            $params['provider'] = $provider->getId();
        }

        if ($id) {
            $where['pa.id'] = ['=', ':id'];
            $params['id'] = $id;
        }

        if ($fid) {
            $where['pa.fid'] = ['=', ':fid'];
            $params['fid'] = $fid;
        }

        if ($onlyPublic) {
            $where['pa.public'] = ['=', ':public'];
            $params['public'] = $onlyPublic;
        }

        $conditions = Utilities::serializeStatementCondition($where);
        $intValues = ['public'];

        $sql = "
            SELECT
                pa.*,
                COALESCE(photo_count, 0) AS photos,
                COALESCE(video_count, 0) AS videos
            FROM picu_album pa
            LEFT JOIN (
                SELECT album, COUNT(*) AS photo_count
                FROM picu_item
                WHERE type = :image
                GROUP BY album
            ) photo_counts ON photo_counts.album = pa.id
            LEFT JOIN (
                SELECT album, COUNT(*) AS video_count
                FROM picu_item
                WHERE type = :video
                GROUP BY album
            ) video_counts ON video_counts.album = pa.id
            WHERE {$conditions} ORDER BY pa.sort
        ";

        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, in_array($key, $intValues) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->bindValue('image', ItemTypeEnum::IMAGE->value);
            $stmt->bindValue('video', ItemTypeEnum::VIDEO->value);
            $stmt->execute();
        } catch (PDOException $e) {
            $this->logger->error('Get provider albums failed: ' . $e->getMessage());
        }

        $albums = [];

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $providerEnum = ProviderEnum::from($row['provider']);
            $album = new Album();
            $album->id = $row['id'];
            $album->fid = $row['fid'];
            $album->setProvider($provider ?? new Provider($providerEnum, $this->settings['providers'][$providerEnum->value]));
            $album->owner = $row['owner'];
            $album->title = $row['title'];
            $album->description = $row['description'];
            $album->cover = $row['cover'];
            $album->photos = $row['photos'];
            $album->videos = $row['videos'];
            $album->public = $row['public'];
            $coverItem = $this->itemService->getByFid($album, $album->cover);
            if ($coverItem) {
                $album->setCoverItem($coverItem);
            }
            $albums[] = $album;
        }

        return $albums;
    }

    public function get(string $albumId): ?Album
    {
        $result = $this->getList(null, $albumId);

        return $result[0] ?? null;
    }

    public function getByFid(string $fid, $onlyPublic = false): ?Album
    {
        $result = $this->getList(null, null, $fid, $onlyPublic);

        return $result[0] ?? null;
    }

    public function create(Album $album): void
    {
        if (!isset($album->id)) {
            $album->id = Utilities::uid();
        }
        $sql = "
            INSERT INTO picu_album (id, fid, provider, title, description, cover, owner, public, sort)
            VALUES (:id, :fid, :provider, :title, :description, :cover, :owner, :public, :sort)
        ";
        $params = [
            'id' => $album->id,
            'fid' => $album->fid,
            'provider' => $album->getProvider()->getId(),
            'title' => $album->title,
            'description' => $album->description ?? null,
            'cover' => $album->cover ?? null,
            'owner' => $album->owner ?? null,
            'public' => $album->public ? 1 : 0,
            'sort' => $album->sort,
        ];
        $intValues = ['public', 'sort'];
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, in_array($key, $intValues) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
        } catch (PDOException $e) {
            $this->logger->error('Add album failed: ' . $e->getMessage());
        }
    }

    public function update(Album $album): void
    {
        if (!isset($album->id)) {
            throw new Exception("Album update failed, missing id: {$album->getProvider()->getId()}, $album->fid");
        }
        $sql = "
            UPDATE picu_album SET
                provider = :provider,
                title = :title,
                description = :description,
                cover = :cover,
                owner = :owner,
                public = :public,
                sort = :sort
            WHERE id = :id
        ";
        $params = [
            'id' => $album->id,
            'provider' => $album->getProvider()->getId(),
            'title' => $album->title,
            'description' => $album->description ?? null,
            'cover' => $album->cover,
            'owner' => $album->owner ?? null,
            'public' => $album->public ? 1 : 0,
            'sort' => $album->sort,
        ];
        $intValues = ['public', 'sort'];
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, in_array($key, $intValues) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logger->error('Update album failed: ' . $e->getMessage());
        }
    }

    /**
     * @param Album $album
     * @return void
     * @throws Exception
     */
    public function delete(Album $album): void
    {
        if (!isset($album->id)) {
            throw new Exception("Album delete failed, missing id: {$album->getProvider()->getId()}, $album->fid");
        }
        $sql = "
            DELETE FROM picu_album WHERE id = :id
        ";
        $params = [
            'id' => $album->id,
        ];
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logger->error('Delete album failed: ' . $e->getMessage());
        }
    }

    public function import(Provider $provider, Album $album, array $items): void
    {
        // delete what's not in imported list
        $this->itemService->deleteAll($album, array_column($items, 'fid'));

        // upsert
        foreach ($items as $item) {
            $dbItems = $this->itemService->getList($album, null, $item->fid);
            if (count($dbItems) === 0) {
                $this->itemService->create($item);
            }
            // if not locally editable, then rewrite item
            elseif (!$provider->isEditable() && count($dbItems) === 1) {
                $item->id = $dbItems[0]->id;
                $this->itemService->update($item);
            }
        }
    }
}
