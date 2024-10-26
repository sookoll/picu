<?php

namespace App\Service;

use App\Enum\ItemTypeEnum;
use App\Model\Album;
use App\Model\Photo;
use App\Model\Provider;
use DateTime;
use http\Exception\RuntimeException;
use JsonException;
use PDO;
use PDOException;

class AlbumService extends BaseService
{
    /**
     * @param Provider $provider
     * @param string|null $albumId
     * @param bool $public
     * @return Album[]
     */
    public function getList(Provider $provider, string $albumId = null, bool $public = false): array
    {
        $where = [
            'pa.provider' => ['=', ':provider']
        ];
        $params = [
            'provider' => $provider->getId()
        ];

        if ($albumId) {
            $where['pa.id'] = ['=', ':id'];
            $params['id'] = $albumId;
        }

        if ($public) {
            $where['pa.public'] = ['=', ':public'];
            $params['public'] = $public;
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
            $album = new Album();
            $album->id = $row['id'];
            $album->fid = $row['fid'];
            $album->provider = $provider;
            $album->owner = $row['owner'];
            $album->title = $row['title'];
            $album->description = $row['description'];
            $album->cover = $row['cover'];
            $album->photos = $row['photos'];
            $album->videos = $row['videos'];
            $albums[] = $album;
        }

        return $albums;
    }

    public function get(Provider $provider, string $albumId): ?Album
    {
        $result = $this->getList($provider, $albumId);

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
            'provider' => $album->provider->getId(),
            'title' => $album->title,
            'description' => $album->description ?? null,
            'cover' => $album->cover ?? null,
            'owner' => $album->owner ?? null,
            'public' => $album->public ?? false,
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
            throw new RuntimeException("Album update failed, missing id: {$album->provider->getId()}, $album->fid");
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
            'provider' => $album->provider->getId(),
            'title' => $album->title,
            'description' => $album->description ?? null,
            'cover' => $album->cover,
            'owner' => $album->owner ?? null,
            'public' => $album->public,
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

    public function delete(Album $album): void
    {
        if (!isset($album->id)) {
            throw new RuntimeException("Album delete failed, missing id: {$album->provider->getId()}, $album->fid");
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

    public function getItemsList(Album $album, string $fid = null): array
    {
        $where = [
            'album' => ['=', ':album']
        ];
        $params = [
            'album' => $album->id
        ];

        if ($fid) {
            $where['fid'] = ['=', ':fid'];
            $params['fid'] = $fid;
        }

        $conditions = Utilities::serializeStatementCondition($where);

        $sql = "SELECT * FROM picu_item WHERE {$conditions} ORDER BY sort";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logger->error('Get provider items failed: ' . $e->getMessage());
        }

        $items = [];

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            try {
                $itemTypeEnum = ItemTypeEnum::from($row['type']);
            } catch (\Exception $e) {
                $this->logger->error('Get stored items error: ' . $e->getMessage());
                continue;
            }
            $item = match ($itemTypeEnum) {
                ItemTypeEnum::IMAGE => new Photo(),
                default => null,
            };
            if ($item) {
                $item->id = $row['id'];
                $item->fid = $row['fid'];
                $item->album = $row['album'];
                $item->title = $row['title'];
                $item->description = $row['description'];
                $item->type = $itemTypeEnum;
                $item->url = $row['url'];
                $item->width = $row['width'];
                $item->height = $row['height'];
                if (!empty($row['datetaken'])) {
                    $item->datetaken = DateTime::createFromFormat('Y-m-d H:i:s', $row['datetaken']);
                }
                $item->metadata = $row['metadata'] && $row['metadata'] !== '' ? json_decode($row['metadata'], true) : null;
                $item->sort = $row['sort'];
                $item->changed = DateTime::createFromFormat('Y-m-d H:i:s', $row['changed']);
                $item->added = DateTime::createFromFormat('Y-m-d H:i:s', $row['added']);
                $items[] = $item;
            }
        }

        return $items;
    }

    public function clear(Album $album, array $exceptFids = []): void
    {
        $where = [
            'album' => ['=', '?']
        ];
        $params = [$album->id];

        if (count($exceptFids)) {
            $placeholders = implode(', ', array_fill(0, count($exceptFids), '?'));
            $where['fid'] = ['NOT IN', "($placeholders)"];
            $params = array_merge($params, $exceptFids);
        }

        $conditions = Utilities::serializeStatementCondition($where);
        $sql = "
            DELETE FROM picu_item WHERE {$conditions}
        ";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logger->error('Clear album failed: ' . $e->getMessage());
        }
    }

    public function import(Provider $provider, Album $album, array $items): void
    {
        // delete
        $this->clear($album, array_column($items, 'fid'));

        // upsert
        foreach ($items as $item) {
            $dbItems = $this->getItemsList($album, $item->fid);
            if (count($dbItems) === 0) {
                $this->createItem($item);
            }
            // if not locally editable, then rewrite item
            elseif (!$provider->isEditable() && count($dbItems) === 1) {
                $item->id = $dbItems[0]->id;
                $this->updateItem($item);
            }
        }
    }

    /**
     * @throws JsonException
     */
    private function createItem(Photo $item): void
    {
        if (!isset($item->id)) {
            $item->id = Utilities::uid();
        }
        $sql = "
            INSERT INTO picu_item (id, fid, album, title, description, type, datetaken, url, width, height, metadata, sort)
            VALUES (:id, :fid, :album, :title, :description, :type, :datetaken, :url, :width, :height, :metadata, :sort)
        ";
        $params = [
            'id' => $item->id,
            'fid' => $item->fid,
            'album' => $item->album,
            'title' => $item->title,
            'description' => $item->description ?? null,
            'type' => $item->type->value,
            'datetaken' => isset($item->datetaken) ? $item->datetaken->format('Y-m-d H:i:s') : null,
            'url' => $item->url,
            'width' => $item->width,
            'height' => $item->height,
            'metadata' => $item->metadata ? json_encode($item->metadata, JSON_THROW_ON_ERROR) : null,
            'sort' => $item->sort,
        ];
        $intValues = ['width', 'height', 'sort'];
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, in_array($key, $intValues) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
        } catch (PDOException $e) {
            $this->logger->error('Add item failed: ' . $e->getMessage());
        }
    }

    private function updateItem(Photo $item): void
    {
        if (!isset($item->id)) {
            throw new RuntimeException("Item update failed, missing id: $item->album, $item->fid");
        }
        $sql = "
            UPDATE picu_item SET
                title = :title,
                description = :description,
                datetaken = :datetaken,
                url = :url,
                width = :width,
                height = :height,
                metadata = :metadata,
                sort = :sort
            WHERE id = :id
        ";
        $params = [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description ?? null,
            'datetaken' => isset($item->datetaken) ? $item->datetaken->format('Y-m-d H:i:s') : null,
            'url' => $item->url,
            'width' => $item->width,
            'height' => $item->height,
            'metadata' => $item->metadata ? json_encode($item->metadata, JSON_THROW_ON_ERROR) : null,
            'sort' => $item->sort,
        ];
        $intValues = ['width', 'height', 'sort'];
        try {
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, in_array($key, $intValues) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
        } catch (PDOException $e) {
            $this->logger->error('Update item failed: ' . $e->getMessage());
        }
    }
}
