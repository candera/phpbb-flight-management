curl -is \
     -X POST \
     -H 'Cookie: phpbb3_j66u3_sid=whatever' \
     -H 'Content-type: application/json' \
     'http://localhost:7777/app.php/vfw440/api/query' \
     --data @- <<EOF
  {
      "from" : "MissionTypes",
      "select" : [ "Id", "Name" ]
  }
EOF

curl -X POST -is 'http://localhost:4444/app.php/vfw440/api/query' -H 'Cookie: phpbb3_j66u3_sid=$PHPBB_SID' -H "Content-Type: application/json" --data @- <<EOF
  {
      "from" : "MissionTypes",
      "select" : ["Id", "Name" ]
  }
EOF
