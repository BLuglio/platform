<?php
/**
 * No frills DB connection class
 *
 * @package diy.org.cashmusic
 * @author CASH Music
 * @link http://cashmusic.org/
 *
 * Copyright (c) 2011, CASH Music
 * Licensed under the Affero General Public License version 3.
 * See http://www.gnu.org/licenses/agpl-3.0.html
 *
 **/
class DBASeed {
	protected $db;
	private $hostname,
			$username,
			$password,
			$dbname,
			$driver,
			$port,
			$error = 'Relax. Everything is okay.';

	public function __construct($hostname,$username,$password,$database,$driver) {
		if (strpos($hostname,':') === false) {
			$this->hostname = $hostname;
			$this->port = 3306;
		} else {
			$host_and_port = explode(':',$hostname);
			$this->hostname = $host_and_port[0];
			$this->port = $host_and_port[1];
		}
		$this->username = $username;
		$this->password = $password;
		$this->dbname = $database;
		$this->driver = $driver;
	}

	public function connect() {
		try {  
			$this->db = new PDO("{$this->driver}:host={$this->hostname};port={$this->port};dbname={$this->dbname}", $this->username, $this->password);
			$this->db->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		} catch(PDOException $e) {  
			$this->error = $e->getMessage();  
			echo $this->error;
			die();
		}
	}

	public function getErrorMessage() {
		return $this->error;
	}
	
	public function lookupTableName($data_name) {
		$table_lookup = array(
			'assets' => 'asst_assets',
			'elements' => 'seed_elements',
			'email_addresses' => 'emal_addresses',
			'events' => 'live_events',
			'lock_codes' => 'lock_codes',
			'lock_passwords' => 'lock_passwords',
			'settings' => 'seed_settings',
			'users' => 'seed_users',
			'venues' => 'live_venues'
		);
		if (array_key_exists($data_name, $table_lookup)) {
		    return $table_lookup[$data_name];
		} else {
			return false;
		}
	}

	public function doQuery($query,$values=false) {
		if ($values) {
			$q = $this->db->prepare($query);
			$q->execute($values);
		} else {
			$q = $this->db->query($query);
		}
		$q->setFetchMode(PDO::FETCH_ASSOC);
		try {  
			$result = $q->fetchAll();
		} catch(PDOException $e) {  
			$this->error = $e->getMessage();
		}
		if ($result) {
			if (count($result) == 0) {
				return false;
			} else {
				return $result;
			}
		}
	}
	
	public function parseConditions($conditions,$prepared=true) {
		$return_str = " WHERE ";
		$separator = '';
		foreach ($conditions as $value => $details) {
			if ($prepared) {
				$return_str .= $separator . $value . ' ' . $details['condition'] . ' ' . ':where' . $value;
			} else {
				if (is_string($details['value'])) {
					$query_value = "'" . str_replace("'","\'",$details['value']) . "'";
				} else {
					$query_value = $details['value'];
				}
				$return_str .= $separator . $value . ' ' . $details['condition'] . ' ' . $query_value;
			}
			$separator = ' AND ';
		}
		return $return_str;
	}

	public function getData($data_name,$data,$conditions=false,$limit=false,$orderby=false) {
		if (!is_object($this->db)) {
			$this->connect();
		}
		$query = false;
		$table_name = $this->lookupTableName($data_name);
		if ($table_name === false) {
			return $this->getSpecialData($data_name,$conditions);
		}
		if ($data) {
			$query = "SELECT $data FROM $table_name";
			if ($conditions) {
				$query .= $this->parseConditions($conditions);
			}
			if ($orderby) $query .= " ORDER BY $orderby";
			if ($limit) $query .= " LIMIT $limit";
		}
		if ($query) {
			if ($conditions) {
				$values_array = array();
				foreach ($conditions as $value => $details) {
					$values_array[':where'.$value] = $details['value'];
				}
				return $this->doQuery($query,$values_array);
			} else {
				return $this->doQuery($query);
			}
		} else {
			return false;
		}
	}
	
	public function setData($data_name,$data,$conditions=false) {
		if (!is_object($this->db)) {
			$this->connect();
		}
		$query = false;
		$table_name = $this->lookupTableName($data_name);
		if (is_array($data) && $table_name) {
			if ($conditions) {
				// if $condition is set then we're doing an UPDATE
				$data['modification_date'] = time();
				$query = "UPDATE $table_name SET ";
				$separator = '';
				foreach ($data as $fieldname => $value) {
					$query .= $separator."$fieldname=:$fieldname";
					$separator = ',';
				}
				$query .= $this->parseConditions($conditions);

				$values_array = array();
				foreach ($conditions as $value => $details) {
					$values_array[':where'.$value] = $details['value'];
				}
				$data = array_merge($data,$values_array);
			} else {
				// no condition? we're doing an INSERT
				$data['creation_date'] = time();
				$query = "INSERT INTO $table_name (";
				$separator = '';
				foreach ($data as $fieldname => $value) {
					$query .= $separator.$fieldname;
					$separator = ',';
				}
				$query .= ") VALUES (";
				$separator = '';
				foreach ($data as $fieldname => $value) {
					$query .= $separator.':'.$fieldname;
					$separator = ',';
				}
				$query .= ")";
			}
			if ($query) {
				try {  
					$q = $this->db->prepare($query);
					$success = $q->execute($data);
					if ($success) {
						if ($conditions) {
							if (array_key_exists('id',$conditions)) {
								return $conditions['id']['value'];
							} else {
								return true;
							}
						} else {
							return $this->db->lastInsertId();
						}
					} else {
						return false;
					}
				} catch(PDOException $e) {  
					$this->error = $e->getMessage();
				}	
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	public function deleteData($data_name,$conditions=false) {
		if (!is_object($this->db)) {
			$this->connect();
		}
		$query = false;
		$table_name = $this->lookupTableName($data_name);
		if ($conditions) {
			$query = "DELETE FROM $table_name" . $this->parseConditions($conditions,false);
			try {  
				$result = $this->db->exec($query);
				if ($result) {
					return true;
				} else {
					return false;
				}
			} catch(PDOException $e) {  
				$this->error = $e->getMessage();  
				echo $this->error;
				die();
			}
		} else {
			return false;
		}
	}
	
	public function getSpecialData($data_name,$conditions=false) {
		if (!is_object($this->db)) {
			$this->connect();
		}
		switch ($data_name) {
			case 'assets_getAssetInfo':
				$query = "SELECT a.user_id,a.parent_id,a.location,a.title,a.description,a.comment,a.seed_settings_id,"
				. "s.name,s.type "
				. "FROM asst_assets a LEFT OUTER JOIN seed_settings s ON a.seed_settings_id = s.id "
				. "WHERE a.id = :asset_id";
				break;
			case 'EventPlant_getAllDates':
				$query = "SELECT d.id,u.display_name as user_display_name,d.date,d.publish,d.cancelled,d.comments,"
				. "v.name as venuename,v.address1,v.address2,v.city,v.region,v.country,v.postalcode,v.website,v.phone "
				. "FROM live_events d JOIN live_venues v ON d.venue_id = v.id JOIN seed_users u ON d.user_id = u.id "
				. "WHERE d.date > {$query_options['cutoffdate']} AND u.id = {$query_options['user_id']} ORDER BY d.date ASC";
				break;
		    case 'EventPlant_getDatesBetween':
				$query = "SELECT d.id,u.display_name as user_display_name,d.date,d.publish,d.cancelled,d.comments,"
				. "v.name as venuename,v.address1,v.address2,v.city,v.region,v.country,v.postalcode,v.website,v.phone "
				. "FROM live_events d JOIN live_venues v ON d.venue_id = v.id JOIN seed_users u ON d.user_id = u.id "
				. "WHERE d.date > {$query_options['afterdate']} AND d.date < {$query_options['beforedate']} "
				. "AND u.id = {$query_options['user_id']} ORDER BY d.date ASC";
				break;
			case 'EventPlant_getDatesByArtistAndDate':
				$query = "SELECT d.id,u.display_name as user_display_name,d.date,d.publish,d.cancelled,d.comments"
				. "v.name as venuename,v.address1,v.address2,v.city,v.region,v.country,v.postalcode,v.website,v.phone "
				. "FROM live_events d JOIN live_venues v ON d.venue_id = v.id JOIN seed_users u ON d.user_id = u.id "
				. "WHERE d.date = {$query_options['date']} AND u.id = {$query_options['user_id']} ORDER BY d.date ASC";
				break;
		    default:
		       return false;
		}
		if ($query) {
			if ($conditions) {
				$values_array = array();
				foreach ($conditions as $value => $details) {
					$values_array[':'.$value] = $details['value'];
				}
				return $this->doQuery($query,$values_array);
			} else {
				return $this->doQuery($query);
			}
		} else {
			return false;
		}
	}
} // END class 
?>