<?php

namespace App\Service;

use App\Enum\ItemTypeEnum;
use App\Model\Album;
use App\Model\Photo;
use App\Model\PhotoSize;
use Exception;
use PDO;
use PDOException;

class ItemService extends BaseService
{

    public function get(string $id): ?Photo
    {
        $result = $this->getList(null, $id);

        return $result[0] ?? null;
    }

    public function getByFid(Album $album, string $fid): ?Photo
    {
        $result = $this->getList($album, null, $fid);

        return $result[0] ?? null;
    }

    /**
     * @param Album|null $album
     * @param string|null $id
     * @param string|null $fid
     * @return Photo[]
     * @throws \JsonException
     */
    public function getList(Album $album = null, string $id = null, string $fid = null): array
    {
        $where = [
            '1' => ['=', '1']
        ];
        $params = [];

        if ($album) {
            $where['album'] = ['=', ':album'];
            $params['album'] = $album->id;
        }

        if ($id) {
            $where['id'] = ['=', ':id'];
            $params['id'] = $id;
        }

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
            } catch (Exception $e) {
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
                $metadata = $row['metadata'] && $row['metadata'] !== ''
                    ? json_decode($row['metadata'], true, 512, JSON_THROW_ON_ERROR)
                    : null;
                if (isset($metadata['sizes'])) {
                    $sizes = [];
                    foreach ($metadata['sizes'] as $key => $val) {
                        $size = new PhotoSize();
                        $size->url = $val['url'];
                        $size->width = $val['width'];
                        $size->height = $val['height'];
                        $sizes[$key] = $size;
                    }
                    $item->sizes = $sizes;
                }
                $item->datetaken = $row['datetaken'];
                $item->sort = $row['sort'];
                $items[] = $item;
            }
        }

        return $items;
    }

    public function getFidList(Album $album): array
    {
        $where = [
            'album' => ['=', ':album']
        ];
        $params = [
            'album' => $album->id
        ];

        $conditions = Utilities::serializeStatementCondition($where);

        $sql = "SELECT fid FROM picu_item WHERE {$conditions} ORDER BY sort";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (PDOException $e) {
            $this->logger->error('Get provider items failed: ' . $e->getMessage());
        }

        $items = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $items[] = $row['fid'];
        }

        return $items;
    }

    public function create(Photo $item): void
    {
        $item->id = $this->ensureUniqueId('picu_album', $item->id ?? null);
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
            'datetaken' => $item->datetaken,
            'url' => $item->url,
            'width' => $item->width,
            'height' => $item->height,
            'metadata' => $item->sizes
                ? json_encode(['sizes' => $item->sizes], JSON_THROW_ON_ERROR)
                : null,
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

    public function update(Photo $item): void
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
            'datetaken' => $item->datetaken,
            'url' => $item->url,
            'width' => $item->width,
            'height' => $item->height,
            'metadata' => $item->sizes
                ? json_encode(['sizes' => $item->sizes], JSON_THROW_ON_ERROR)
                : null,
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

    public function deleteAll(Album $album, array $exceptFids = []): void
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
}
