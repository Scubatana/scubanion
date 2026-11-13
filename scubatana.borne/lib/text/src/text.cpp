#include "text.h"

namespace text {

namespace {

bool isPunctuation(const std::string& word)
{
	return word == "!" || word == "?" || word == ":" || word == ";";
}

}

std::vector<std::string> wrap(const std::string& text, int maxWidth, const std::function<int(const std::string&)>& width)
{
	std::vector<std::string> lines;
	std::string line;
	size_t start = 0;

	while (start < text.size()) {
		size_t end = text.find(' ', start);

		if (end == std::string::npos) {
			end = text.size();
		}

		const std::string word = text.substr(start, end - start);
		start = end + 1;

		if (word.empty()) {
			continue;
		}

		const std::string candidate = line.empty() ? word : line + " " + word;

		if (line.empty() || isPunctuation(word) || width(candidate) <= maxWidth) {
			line = candidate;
			continue;
		}

		lines.push_back(line);
		line = word;
	}

	if (!line.empty()) {
		lines.push_back(line);
	}

	return lines;
}

}
