from xml.etree import ElementTree
from bs4 import BeautifulSoup
import requests
from io import StringIO
from datetime import date
from typing import Set, Union, Dict, Any
from abc import abstractmethod
from .types import Member, Country

ResourceUrls = Union[Dict[str, str], Dict[int, str]]
LoadedResources = Union[Dict[str, str], Dict[int, str]]
ParsedResources = Union[Dict[str, Any], Dict[int, str]]


class Scraper:
    _parsed: ParsedResources = {}

    def run(self) -> Any:
        self._load_resources()
        return self._extract_information()

    @abstractmethod
    def _extract_information(self) -> Any:
        pass

    @abstractmethod
    def _resource_urls(self) -> ResourceUrls:
        pass

    def _load_resources(self) -> None:
        urls = self._resource_urls()
        self._resources = {k: self._load_resource(v) for k, v in urls.items()}

    def _load_resource(self, resource_url: str) -> str:
        raw = requests.get(resource_url).text
        return self._parse_resource(raw)

    @abstractmethod
    def _parse_resource(self, resource: str) -> Any:
        pass


class MembersScraper(Scraper):
    TERMS = [8, 9]
    DIRECTORY_BASE_URL = "https://europarl.europa.eu/meps/en/directory/xml"

    def _extract_information(self) -> Dict:
        self._members = {}

        for term in self.TERMS:
            for member in self._get_members(term):
                self._add_member(member)

        return list(self._members.values())

    def _add_member(self, member):
        web_id = member.europarl_website_id

        if web_id not in self._members:
            self._members[web_id] = member
            return

        terms = self._members[web_id].terms | member.terms
        self._members[web_id].terms = terms

    def _get_members(self, term):
        tags = self._resources[term].findall("mep")
        return [self._get_member(tag, term) for tag in tags]

    def _get_member(self, tag: ElementTree, term):
        europarl_website_id = int(tag.find("id").text)
        return Member(europarl_website_id=europarl_website_id, terms={term})

    def _parse_resource(self, resource: str) -> Any:
        fd = StringIO(resource)
        return ElementTree.parse(fd)

    def _resource_urls(self) -> ResourceUrls:
        base = self.DIRECTORY_BASE_URL
        return {term: f"{base}/?leg={term}" for term in self.TERMS}


class MemberInfoScraper(Scraper):
    PROFILE_BASE_URL = "https://europarl.europa.eu/meps/en"

    def __init__(self, europarl_website_id: int, terms: Set[int]):
        self.europarl_website_id = europarl_website_id
        self.terms = terms

    def _extract_information(self) -> Member:
        first_name, last_name = Member.parse_full_name(self._full_name())

        return Member(
            europarl_website_id=self.europarl_website_id,
            terms=set(self.terms),
            first_name=first_name,
            last_name=last_name,
            date_of_birth=self._date_of_birth(),
            country=self._country(),
        )

    def _parse_resource(self, resource: str) -> BeautifulSoup:
        return BeautifulSoup(resource, "lxml")

    def _resource_urls(self) -> ResourceUrls:
        web_id = self.europarl_website_id
        base = self.PROFILE_BASE_URL

        return {term: f"{base}/{web_id}/NAME/history/{term}" for term in self.terms}

    def _latest_term(self) -> BeautifulSoup:
        return self._resources[max(self.terms)]

    def _full_name(self) -> str:
        html = self._latest_term()
        return html.select("#presentationmep div.erpl_title-h1")[0].text.strip()

    def _date_of_birth(self) -> date:
        raw = self._latest_term().select("#birthDate")

        if not raw:
            return

        raw = raw[0].text.strip()
        year = int(raw[6:])
        month = int(raw[3:5])
        day = int(raw[:2])

        return date(year, month, day)

    def _country(self) -> Country:
        html = self._latest_term()
        raw = html.select("#presentationmep div.erpl_title-h3")[0].text
        country = raw.split("-")[0].strip()

        return Country.from_str(country)
