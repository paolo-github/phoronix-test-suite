<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2008, Phoronix Media
	Copyright (C) 2008, Michael Larabel
	pts-functions_tests.php: Functions needed for some test parameters

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

function pts_save_result($save_to = null, $save_results = null)
{
	// Saves PTS result file
	if(strpos($save_to, ".xml") === false)
	{
		$save_to .= ".xml";
	}

	$save_to_dir = dirname(SAVE_RESULTS_DIR . $save_to);

	if(!is_dir(SAVE_RESULTS_DIR))
	{
		mkdir(SAVE_RESULTS_DIR);
	}
	if($save_to_dir != '.' && !is_dir($save_to_dir))
	{
		mkdir($save_to_dir);
	}

	if(!is_dir(SAVE_RESULTS_DIR . "pts-results-viewer"))
	{
		mkdir(SAVE_RESULTS_DIR . "pts-results-viewer");
	}

	pts_copy(RESULTS_VIEWER_DIR . "pts.js", SAVE_RESULTS_DIR . "pts-results-viewer/pts.js");
	pts_copy(RESULTS_VIEWER_DIR . "pts-results-viewer.xsl", SAVE_RESULTS_DIR . "pts-results-viewer/pts-results-viewer.xsl");
	pts_copy(RESULTS_VIEWER_DIR . "pts-viewer.css", SAVE_RESULTS_DIR . "pts-results-viewer/pts-viewer.css");
	pts_copy(RESULTS_VIEWER_DIR . "pts-logo.png", SAVE_RESULTS_DIR . "pts-results-viewer/pts-logo.png");
	
	if($save_to == null || $save_results == null)
	{
		$bool = true;
	}
	else
	{
		$save_name = basename($save_to, ".xml");

		if($save_name == "composite")
		{
			if(!is_dir($save_to_dir . "/result-graphs"))
			{
				mkdir($save_to_dir . "/result-graphs");
			}

			$xml_reader = new tandem_XmlReader($save_results);
			$results_suite_name = $xml_reader->getXMLValue(P_RESULTS_SUITE_NAME);
			$results_name = $xml_reader->getXMLArrayValues(P_RESULTS_TEST_TITLE);
			$results_testname = $xml_reader->getXMLArrayValues(P_RESULTS_TEST_TESTNAME);
			$results_version = $xml_reader->getXMLArrayValues(P_RESULTS_TEST_VERSION);
			$results_attributes = $xml_reader->getXMLArrayValues(P_RESULTS_TEST_ATTRIBUTES);
			$results_scale = $xml_reader->getXMLArrayValues(P_RESULTS_TEST_SCALE);
			$results_proportion = $xml_reader->getXMLArrayValues(P_RESULTS_TEST_PROPORTION);
			$results_result_format = $xml_reader->getXMLArrayValues(P_RESULTS_TEST_RESULTFORMAT);
			$results_raw = $xml_reader->getXMLArrayValues(P_RESULTS_RESULTS_GROUP);

			$results_identifiers = array();
			$results_values = array();

			foreach($results_raw as $result_raw)
			{
				$xml_results = new tandem_XmlReader($result_raw);
				array_push($results_identifiers, $xml_results->getXMLArrayValues(S_RESULTS_RESULTS_GROUP_IDENTIFIER));
				array_push($results_values, $xml_results->getXMLArrayValues(S_RESULTS_RESULTS_GROUP_VALUE));
			}

			for($i = 0; $i < count($results_name); $i++)
			{
				if(strlen($results_version[$i]) > 2)
				{
					$results_name[$i] .= " v" . $results_version[$i];
				}

				if($results_result_format[$i] == "LINE_GRAPH")
				{
					$t = new pts_LineGraph($results_name[$i], $results_attributes[$i], $results_scale[$i]);
				}
				else if($results_result_format[$i] == "PASS_FAIL")
				{
					$t = new pts_PassFailGraph($results_name[$i], $results_attributes[$i], $results_scale[$i]);
				}
				else if($results_result_format[$i] == "MULTI_PASS_FAIL")
				{
					$t = new pts_MultiPassFailGraph($results_name[$i], $results_attributes[$i], $results_scale[$i]);
				}
				else
				{
					$t = new pts_BarGraph($results_name[$i], $results_attributes[$i], $results_scale[$i]);
				}

				if(pts_gd_available() && getenv("SVG_DEBUG") == false)
				{
					// Render to PNG
					$t->setRenderer("PNG");
					pts_copy(RESULTS_VIEWER_DIR . "pts-results-viewer.xsl", $save_to_dir . "/pts-results-viewer.xsl");
				}
				else
				{
					if(!defined("PHP_SVG_TEXT"))
					{
						echo "\nThe PHP GD extension is missing, so the experimental SVG rendering engine is being used.\n";
						define("PHP_SVG_TEXT", 1);
					}

					// Render to SVG
					$t->setRenderer("SVG");
					pts_copy(RESULTS_VIEWER_DIR . "pts-svg-results-viewer.xsl", $save_to_dir . "/pts-results-viewer.xsl");
				}

				$t->loadGraphIdentifiers($results_identifiers[$i]);
				$t->loadGraphValues($results_values[$i]);
				$t->loadGraphProportion($results_proportion[$i]);
				$t->loadGraphVersion(PTS_VERSION);

				$t->add_user_identifier("Test", $results_testname[$i]);
				$t->add_user_identifier("Identifier", $results_suite_name);
				$t->add_user_identifier("User", pts_current_user());

				$t->save_graph($save_to_dir . "/result-graphs/" . ($i + 1) . "." . strtolower($t->getRenderer()));
				$t->renderGraph();
			}
		}
		$bool = file_put_contents(SAVE_RESULTS_DIR . $save_to, $save_results);

		if(defined("TEST_RESULTS_IDENTIFIER") && (pts_string_bool(pts_read_user_config(P_OPTION_LOG_VSYSDETAILS, "TRUE")) || (defined("IS_PCQS_MODE") && IS_PCQS_MODE) || getenv("SAVE_SYSTEM_DETAILS") != false))
		{
			// Save verbose system information here
			if(!is_dir($save_to_dir . "/system-details/"))
			{
				mkdir($save_to_dir . "/system-details/");
			}
			if(!is_dir($save_to_dir . "/system-details/" . TEST_RESULTS_IDENTIFIER))
			{
				mkdir($save_to_dir . "/system-details/" . TEST_RESULTS_IDENTIFIER);
			}

			if(is_file("/var/log/Xorg.0.log"))
			{
				pts_copy("/var/log/Xorg.0.log", $save_to_dir . "/system-details/" . TEST_RESULTS_IDENTIFIER . "/Xorg.0.log");
			}

			// lspci
			$file = shell_exec("lspci 2>&1");

			if(strpos($file, "not found") == false)
			{
				@file_put_contents($save_to_dir . "/system-details/" . TEST_RESULTS_IDENTIFIER . "/lspci", $file);
			}

			// sensors
			$file = shell_exec("sensors 2>&1");

			if(strpos($file, "not found") == false)
			{
				@file_put_contents($save_to_dir . "/system-details/" . TEST_RESULTS_IDENTIFIER . "/sensors", $file);
			}

			// dmesg
			$file = shell_exec("dmesg 2>&1");

			if(strpos($file, "not found") == false)
			{
				@file_put_contents($save_to_dir . "/system-details/" . TEST_RESULTS_IDENTIFIER . "/dmesg", $file);
			}

			if(IS_MACOSX)
			{
				// system_profiler (Mac OS X)
				$file = shell_exec("system_profiler 2>&1");

				if(strpos($file, "not found") == false)
				{
					@file_put_contents($save_to_dir . "/system-details/" . TEST_RESULTS_IDENTIFIER . "/system_profiler", $file);
				}
			}

			// cpuinfo
			if(is_file("/proc/cpuinfo"))
			{
				$file = file_get_contents("/proc/cpuinfo");
				@file_put_contents($save_to_dir . "/system-details/" . TEST_RESULTS_IDENTIFIER . "/cpuinfo", $file);
			}
		}
		file_put_contents($save_to_dir . "/index.html", "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.0 Transitional//EN\"><html><head><title>Phoronix Test Suite</title><meta http-equiv=\"REFRESH\" content=\"0;url=composite.xml\"></HEAD><BODY></BODY></HTML>");
	}

	return $bool;
}
function pts_global_upload_result($result_file, $tags = "")
{
	// Upload a test result to the Phoronix Global database
	$test_results = file_get_contents($result_file);
	$test_results = str_replace(array("\n", "\t"), "", $test_results);
	$switch_tags = array("Benchmark>" => "B>", "Results>" => "R>", "Group>" => "G>", "Entry>" => "E>", "Identifier>" => "I>", "Value>" => "V>", "System>" => "S>", "Attributes>" => "A>");

	foreach($switch_tags as $f => $t)
	{
		$test_results = str_replace($f, $t, $test_results);
	}

	$ToUpload = base64_encode($test_results);
	$GlobalUser = pts_current_user();
	$GlobalKey = pts_read_user_config(P_OPTION_GLOBAL_UPLOADKEY, "");
	$tags = base64_encode($tags);
	$return_stream = "";

	$upload_data = array("result_xml" => $ToUpload, "global_user" => $GlobalUser, "global_key" => $GlobalKey, "tags" => $tags);
	$upload_data = http_build_query($upload_data);

	$http_parameters = array("http" => array("method" => "POST", "content" => $upload_data));

	$stream_context = stream_context_create($http_parameters);
	$opened_url = @fopen("http://www.phoronix-test-suite.com/global/user-upload.php", "rb", false, $stream_context);
	$response = @stream_get_contents($opened_url);

	if($response !== false)
	{
		$return_stream = $response;
	}

	return $return_stream;
}
function pts_test_needs_updated_install($identifier)
{
	// Checks if test needs updating
	return !is_file(TEST_ENV_DIR . $identifier . "/pts-install.xml")  || !pts_version_comparable(pts_test_profile_version($identifier), pts_test_installed_profile_version($identifier)) || pts_test_checksum_installer($identifier) != pts_test_installed_checksum_installer($identifier) || pts_test_installed_system_identifier($identifier) != pts_system_identifier_string();
}
function pts_test_checksum_installer($identifier)
{
	// Calculate installed checksum
	$md5_checksum = "";

	if(is_file(pts_location_test_resources($identifier) . "install.php"))
	{
		$md5_checksum = md5_file(pts_location_test_resources($identifier) . "install.php");
	}
	else if(is_file(pts_location_test_resources($identifier) . "install.sh"))
	{
		$md5_checksum = md5_file(pts_location_test_resources($identifier) . "install.sh");
	}

	return $md5_checksum;
}
function pts_test_installed_checksum_installer($identifier)
{
	// Read installer checksum of installed tests
	$version = "";

	if(is_file(TEST_ENV_DIR . $identifier . "/pts-install.xml"))
	{
	 	$xml_parser = new tandem_XmlReader(TEST_ENV_DIR . $identifier . "/pts-install.xml", false);
		$version = $xml_parser->getXMLValue(P_INSTALL_TEST_CHECKSUM);
	}

	return $version;
}
function pts_test_installed_system_identifier($identifier)
{
	// Read installer checksum of installed tests
	$value = "";

	if(is_file(TEST_ENV_DIR . $identifier . "/pts-install.xml"))
	{
	 	$xml_parser = new tandem_XmlReader(TEST_ENV_DIR . $identifier . "/pts-install.xml", false);
		$value = $xml_parser->getXMLValue(P_INSTALL_TEST_SYSIDENTIFY);
	}

	return $value;
}
function pts_test_profile_version($identifier)
{
	// Checks PTS profile version
	$version = "";

	if(is_test($identifier))
	{
	 	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($identifier));
		$version = $xml_parser->getXMLValue(P_TEST_PTSVERSION);
	}

	return $version;
}
function pts_test_installed_profile_version($identifier)
{
	// Checks installed version
	$version = "";

	if(is_file(TEST_ENV_DIR . $identifier . "/pts-install.xml"))
	{
	 	$xml_parser = new tandem_XmlReader(TEST_ENV_DIR . $identifier . "/pts-install.xml", false);
		$version = $xml_parser->getXMLValue(P_INSTALL_TEST_VERSION);
	}

	return $version;
}
function pts_test_generate_install_xml($identifier)
{
	// Generate an install XML for pts-install.xml
	/*$xml_writer = new tandem_XmlWriter();

	$xml_writer->addXmlObject(P_INSTALL_TEST_NAME, 1, $identifier);
	$xml_writer->addXmlObject(P_INSTALL_TEST_VERSION, 1, pts_test_profile_version($identifier));
	$xml_writer->addXmlObject(P_INSTALL_TEST_CHECKSUM, 1, pts_test_checksum_installer($identifier));
	$xml_writer->addXmlObject(P_INSTALL_TEST_SYSIDENTIFY, 1, pts_system_identifier_string());
	$xml_writer->addXmlObject(P_INSTALL_TEST_INSTALLTIME, 2, date("Y-m-d H:i:s"));
	$xml_writer->addXmlObject(P_INSTALL_TEST_LASTRUNTIME, 2, "0000-00-00 00:00:00");
	$xml_writer->addXmlObject(P_INSTALL_TEST_TIMESRUN, 2, "0");

	file_put_contents(TEST_ENV_DIR . $identifier . "/pts-install.xml", $xml_writer->getXML());*/
	return pts_test_refresh_install_xml($identifier, 0, true);
}
function pts_test_refresh_install_xml($identifier, $this_test_duration = 0, $new_install = false)
{
	// Generate/refresh an install XML for pts-install.xml
 	$xml_parser = new tandem_XmlReader(TEST_ENV_DIR . $identifier . "/pts-install.xml", false);
	$xml_writer = new tandem_XmlWriter();

	$test_duration = $xml_parser->getXMLValue(P_INSTALL_TEST_AVG_RUNTIME);
	if(!is_numeric($test_duration))
	{
		$test_duration = $this_test_duration;
	}
	if(is_numeric($this_test_duration) && $this_test_duration > 0)
	{
		$test_duration = ceil((($test_duration * $xml_parser->getXMLValue(P_INSTALL_TEST_TIMESRUN)) + $this_test_duration) / ($xml_parser->getXMLValue(P_INSTALL_TEST_TIMESRUN) + 1));
	}

	$test_version = $xml_parser->getXMLValue(P_INSTALL_TEST_VERSION);
	if(empty($test_version) || $new_install)
	{
		$test_version = pts_test_profile_version($identifier);
	}

	$test_checksum = $xml_parser->getXMLValue(P_INSTALL_TEST_CHECKSUM);
	if(empty($test_checksum) || $new_install)
	{
		$test_checksum = pts_test_checksum_installer($identifier);
	}

	$sys_identifier = $xml_parser->getXMLValue(P_INSTALL_TEST_SYSIDENTIFY);
	if(empty($sys_identifier) || $new_install)
	{
		$sys_identifier = pts_system_identifier_string();
	}

	$install_time = $xml_parser->getXMLValue(P_INSTALL_TEST_INSTALLTIME);
	if(empty($install_time))
	{
		$install_time = date("Y-m-d H:i:s");
	}

	$times_run = $xml_parser->getXMLValue(P_INSTALL_TEST_TIMESRUN);
	if($new_install && empty($times_run))
	{
		$times_run = 0;
	}
	if(!$new_install)
		$times_run++;

	$xml_writer->addXmlObject(P_INSTALL_TEST_NAME, 1, $identifier);
	$xml_writer->addXmlObject(P_INSTALL_TEST_VERSION, 1, $test_version);
	$xml_writer->addXmlObject(P_INSTALL_TEST_CHECKSUM, 1, $test_checksum);
	$xml_writer->addXmlObject(P_INSTALL_TEST_SYSIDENTIFY, 1, $sys_identifier);
	$xml_writer->addXmlObject(P_INSTALL_TEST_INSTALLTIME, 2, $install_time);
	$xml_writer->addXmlObject(P_INSTALL_TEST_LASTRUNTIME, 2, date("Y-m-d H:i:s"));
	$xml_writer->addXmlObject(P_INSTALL_TEST_TIMESRUN, 2, $times_run);
	$xml_writer->addXmlObject(P_INSTALL_TEST_AVG_RUNTIME, 2, $test_duration, 2);

	file_put_contents(TEST_ENV_DIR . $identifier . "/pts-install.xml", $xml_writer->getXML());
}
function pts_test_name_to_identifier($name)
{
	// Convert test name to identifier
	$identifier = false;

	if(!empty($name))
	{
		foreach(glob(XML_PROFILE_DIR . "*.xml") as $test_profile_file)
		{
		 	$xml_parser = new tandem_XmlReader($test_profile_file);

			if($xml_parser->getXMLValue(P_TEST_TITLE) == $name)
			{
				$identifier = basename($test_profile_file, ".xml");
			}
		}
	}

	return $identifier;
}
function pts_test_identifier_to_name($identifier)
{
	// Convert identifier to test name
	$name = false;

	if(!empty($identifier) && is_test($identifier))
	{
	 	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($identifier));
		$name = $xml_parser->getXMLValue(P_TEST_TITLE);
	}

	return $name;
}
function pts_estimated_download_size($identifier)
{
	// Estimate the size of files to be downloaded
	$estimated_size = 0;
	foreach(pts_contained_tests($identifier, true) as $test)
	{
	 	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($test));
		$this_size = $xml_parser->getXMLValue(P_TEST_DOWNLOADSIZE); // TODO: The DownloadSize tag has been deprecates as of Phoronix Test Suite 1.4.0

		if(!empty($this_size) && is_numeric($this_size))
		{
			$estimated_size += $this_size;
		}
		else
		{
			// The work for calculating the download size in 1.4.0+
			foreach(pts_objects_test_downloads($test) as $download_object)
			{
				$estimated_size += pts_trim_double($download_object->get_filesize() / 1048576);
			}
		}
	}

	return $estimated_size;
}
function pts_test_estimated_environment_size($identifier)
{
	// Estimate the environment size of a test or suite
	$estimated_size = 0;

	foreach(pts_contained_tests($identifier, true) as $test)
	{
	 	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($identifier));
		$this_size = $xml_parser->getXMLValue(P_TEST_ENVIRONMENTSIZE);

		if(!empty($this_size) && is_numeric($this_size))
		{
			$estimated_size += $this_size;
		}
	}

	return $estimated_size;
}
function pts_test_architecture_supported($identifier)
{
	// Check if the system's architecture is supported by a test
	$supported = true;

	if(is_test($identifier))
	{
	 	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($identifier));
		$archs = $xml_parser->getXMLValue(P_TEST_SUPPORTEDARCHS);

		if(!empty($archs))
		{
			$archs = explode(",", $archs);

			foreach($archs as $key => $value)
			{
				$archs[$key] = trim($value);
			}

			$supported = pts_cpu_arch_compatible($archs);
		}
	}

	return $supported;
}
function pts_test_platform_supported($identifier)
{
	// Check if the system's OS is supported by a test
	$supported = true;

	if(is_test($identifier))
	{
	 	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($identifier));
		$platforms = $xml_parser->getXMLValue(P_TEST_SUPPORTEDPLATFORMS);
		$un_platforms = $xml_parser->getXMLValue(P_TEST_UNSUPPORTEDPLATFORMS);

		if(OPERATING_SYSTEM != "Unknown")
		{
			if(!empty($un_platforms))
			{
				$un_platforms = explode(",", $un_platforms);

				foreach($un_platforms as $key => $value)
				{
					$un_platforms[$key] = trim($value);
				}

				if(in_array(OPERATING_SYSTEM, $un_platforms))
				{
					$supported = false;
				}
			}
			if(!empty($platforms))
			{
				$platforms = explode(",", $platforms);

				foreach($platforms as $key => $value)
				{
					$platforms[$key] = trim($value);
				}

				if(!in_array(OPERATING_SYSTEM, $platforms))
				{
					$supported = false;
				}
			}
		}
	}

	return $supported;
}
function pts_test_version_supported($identifier)
{
	// Check if the test profile's version is compatible with pts-core
	$supported = true;

	if(is_test($identifier))
	{
	 	$xml_parser = new pts_test_tandem_XmlReader(pts_location_test($identifier));
		$requires_core_version = $xml_parser->getXMLValue(P_TEST_SUPPORTS_COREVERSION);

		$supported = pts_test_version_compatible($requires_core_version);
	}

	return $supported;
}
function pts_suite_supported($identifier)
{
	$tests = pts_contained_tests($identifier, true);
	$supported_size = $original_size = count($tests);

	for($i = 0; $i < $original_size; $i++)
	{
		if(!pts_test_supported(@$tests[$i]))
		{
			$supported_size--;
		}
	}

	if($supported_size == 0)
	{
		$return_code = 0;
	}
	else if($supported_size != $original_size)
	{
		$return_code = 1;
	}
	else
	{
		$return_code = 2;
	}

	return $return_code;
}
function pts_test_supported($identifier)
{
	return pts_test_architecture_supported($identifier) && pts_test_platform_supported($identifier) && pts_test_version_supported($identifier);
}
function pts_available_tests_array()
{
	$tests = glob(XML_PROFILE_DIR . "*.xml");
	$local_tests = glob(XML_PROFILE_LOCAL_DIR . "*.xml");
	$tests = array_unique(array_merge($tests, $local_tests));
	asort($tests);

	for($i = 0; $i < count($tests); $i++)
	{
		$tests[$i] = basename($tests[$i], ".xml");
	}

	return $tests;
}
function pts_installed_tests_array()
{
	$tests = glob(TEST_ENV_DIR . "*/pts-install.xml");

	for($i = 0; $i < count($tests); $i++)
	{
		$install_file_arr = explode("/", $tests[$i]);
		$tests[$i] = $install_file_arr[count($install_file_arr) - 2];
	}

	return $tests;
}
function pts_available_suites_array()
{
	$suites = glob(XML_SUITE_DIR . "*.xml");
	$local_suites = glob(XML_SUITE_LOCAL_DIR . "*.xml");
	$suites = array_unique(array_merge($suites, $local_suites));
	asort($suites);

	for($i = 0; $i < count($suites); $i++)
	{
		$suites[$i] = basename($suites[$i], ".xml");
	}

	return $suites;
}
function pts_test_version_compatible($version_compare = "")
{
	$compatible = true;

	if(!empty($version_compare))
	{
		$current = preg_replace("/[^0-9]/", "", PTS_VERSION);

		$version_compare = explode("-", $version_compare);	
		$support_begins = preg_replace("/[^0-9]/", "", trim($version_compare[0]));

		if(count($version_compare) == 2)
		{
			$support_ends = trim($version_compare[1]);
		}
		else
		{
			$support_ends = PTS_VERSION;
		}

		$support_ends = preg_replace("/[^0-9]/", "", $support_ends);

		if($current >= $support_begins && $current <= $support_ends)
		{
			$compatible = true;
		}
		else
		{
			$compatible = false;
		}
	}

	return $compatible;	
}
function pts_call_test_script($test_identifier, $script_name, $print_string = "", $pass_argument = "", $extra_vars = null, $use_ctp = true)
{
	$result = null;
	$test_directory = TEST_ENV_DIR . $test_identifier . "/";

	if($use_ctp)
	{
		$tests_r = pts_contained_tests($test_identifier, true);
	}
	else
	{
		$tests_r = array($test_identifier);
	}

	foreach($tests_r as $this_test)
	{
		if(is_file(($run_file = pts_location_test_resources($this_test) . $script_name . ".php")) || is_file(($run_file = pts_location_test_resources($this_test) . $script_name . ".sh")))
		{
			$file_extension = substr($run_file, (strrpos($run_file, ".") + 1));

			if(!empty($print_string))
			{
				echo $print_string;
			}

			if($file_extension == "php")
			{
				$this_result = pts_exec("cd " .  $test_directory . " && " . PHP_BIN . " " . $run_file . " \"" . $pass_argument . "\"", $extra_vars);
			}
			else if($file_extension == "sh")
			{
				$this_result = pts_exec("cd " .  $test_directory . " && sh " . $run_file . " \"" . $pass_argument . "\"", $extra_vars);
			}
			else
			{
				$this_result = null;
			}

			if(trim($this_result) != "")
			{
				$result = $this_result;
			}
		}
	}

	return $result;
}
function pts_cpu_arch_compatible($check_against)
{
	$compatible = true;
	$this_arch = kernel_arch();

	if(strlen($this_arch) > 3 && substr($this_arch, -2) == "86")
	{
		$this_arch = "x86";
	}
	if(!is_array($check_against))
	{
		$check_against = array($check_against);
	}
	if(!in_array($this_arch, $check_against))
	{
		$compatible = false;
	}

	return $compatible;
}
function pts_objects_test_downloads($test_identifier)
{
	$obj_r = array();

	if(is_file(($download_xml_file = pts_location_test_resources($test_identifier) . "downloads.xml")))
	{
		$xml_parser = new tandem_XmlReader($download_xml_file);
		$package_url = $xml_parser->getXMLArrayValues(P_DOWNLOADS_PACKAGE_URL);
		$package_md5 = $xml_parser->getXMLArrayValues(P_DOWNLOADS_PACKAGE_MD5);
		$package_filename = $xml_parser->getXMLArrayValues(P_DOWNLOADS_PACKAGE_FILENAME);
		$package_filesize = $xml_parser->getXMLArrayValues(P_DOWNLOADS_PACKAGE_FILESIZE);
		$package_platform = $xml_parser->getXMLArrayValues(P_DOWNLOADS_PACKAGE_PLATFORMSPECIFIC);
		$package_architecture = $xml_parser->getXMLArrayValues(P_DOWNLOADS_PACKAGE_ARCHSPECIFIC);

		for($i = 0; $i < count($package_url); $i++)
		{
			$file_exempt = false;

			if(!empty($package_platform[$i]))
			{
				$platforms = explode(",", $package_platform[$i]);

				foreach($platforms as $key => $value)
				{
					$platforms[$key] = trim($value);
				}

				$file_exempt = !in_array(OPERATING_SYSTEM, $platforms);
			}

			if(!empty($package_architecture[$i]))
			{
				$architectures = explode(",", $package_architecture[$i]);

				foreach($architectures as $key => $value)
				{
					$architectures[$key] = trim($value);
				}

				$file_exempt = !pts_cpu_arch_compatible($architectures);
			}

			if(!$file_exempt)
			{
				array_push($obj_r, new pts_test_file_download($package_url[$i], $package_filename[$i], $package_filesize[$i], $package_md5[$i]));
			}
		}
	}

	return $obj_r;
}

?>
